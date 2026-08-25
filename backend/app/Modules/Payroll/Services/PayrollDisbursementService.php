<?php

namespace App\Modules\Payroll\Services;

use App\Models\User;
use App\Modules\Payroll\Enums\PayrollDisbursementStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Exceptions\PayrollValidationException;
use App\Modules\Payroll\Models\CompanyBankSetting;
use App\Modules\Payroll\Models\PayrollDisbursementBatch;
use App\Modules\Payroll\Models\PayrollDisbursementItem;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Support\Facades\DB;

class PayrollDisbursementService
{
    /**
     * Generate batch baru — snapshot penuh data rekening + jumlah transfer
     * dari revisi AKTIF saat ini. Boleh dipanggil berkali-kali (misal HR
     * generate ulang setelah recalculate) — tiap panggilan bikin batch BARU,
     * batch lama tidak pernah diubah/dihapus (konsisten prinsip immutability
     * revisi/payslip di seluruh module ini).
     */
    public function generate(PayrollRun $run, ?User $actor): PayrollDisbursementBatch
    {
        if ($run->status !== PayrollRunStatus::Locked) {
            throw new PayrollValidationException('Payroll run harus berstatus Locked sebelum bisa generate file disbursement.');
        }

        $bankSetting = CompanyBankSetting::where('company_id', $run->company_id)->first();
        if (! $bankSetting) {
            throw new PayrollValidationException('Rekening sumber company belum diatur — lengkapi dulu di Pengaturan Bank sebelum generate disbursement.');
        }

        $revision = $run->currentRevision;
        if (! $revision) {
            throw new PayrollValidationException('Payroll run ini belum punya revisi payslip.');
        }

        $payslips = $revision->payslips()->with('employee')->get();

        if ($payslips->isEmpty()) {
            throw new PayrollValidationException('Tidak ada payslip di revisi aktif untuk di-generate.');
        }

        $missingBank = $payslips->filter(fn ($p) => blank($p->employee?->bank_account_number));
        if ($missingBank->isNotEmpty()) {
            $names = $missingBank->map(fn ($p) => trim($p->employee->first_name.' '.$p->employee->last_name))->implode(', ');
            throw new PayrollValidationException("Employee berikut belum punya data rekening bank lengkap: {$names}");
        }

        return DB::transaction(function () use ($run, $revision, $payslips, $actor) {
            $batch = PayrollDisbursementBatch::create([
                'payroll_run_id' => $run->id,
                'payroll_run_revision_id' => $revision->id,
                'status' => PayrollDisbursementStatus::Generated->value,
                'total_amount' => $payslips->sum('net_pay'),
                'total_employee_count' => $payslips->count(),
                'generated_by_user_id' => $actor?->id,
                'generated_at' => now(),
            ]);

            foreach ($payslips as $payslip) {
                PayrollDisbursementItem::create([
                    'payroll_disbursement_batch_id' => $batch->id,
                    'employee_id' => $payslip->employee_id,
                    'payslip_id' => $payslip->id,
                    'employee_name' => trim($payslip->employee->first_name.' '.$payslip->employee->last_name),
                    'bank_name' => $payslip->employee->bank_name,
                    'account_number' => $payslip->employee->bank_account_number,
                    'account_holder_name' => $payslip->employee->bank_account_holder_name,
                    'amount' => $payslip->net_pay,
                ]);
            }

            return $batch->fresh('items');
        });
    }

    public function markSent(PayrollDisbursementBatch $batch, User $actor): PayrollDisbursementBatch
    {
        if ($batch->status !== PayrollDisbursementStatus::Generated) {
            throw new PayrollValidationException('Batch ini sudah bukan status Digenerate — tidak bisa ditandai Terkirim lagi.');
        }

        $batch->update([
            'status' => PayrollDisbursementStatus::Sent->value,
            'sent_by_user_id' => $actor->id,
            'sent_at' => now(),
        ]);

        return $batch->fresh();
    }

    public function markConfirmed(PayrollDisbursementBatch $batch, User $actor): PayrollDisbursementBatch
    {
        if ($batch->status !== PayrollDisbursementStatus::Sent) {
            throw new PayrollValidationException('Batch harus berstatus Sudah Dikirim dulu sebelum bisa dikonfirmasi.');
        }

        $batch->update([
            'status' => PayrollDisbursementStatus::Confirmed->value,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
        ]);

        return $batch->fresh();
    }

    public function markFailed(PayrollDisbursementBatch $batch, User $actor, string $reason): PayrollDisbursementBatch
    {
        if ($batch->status !== PayrollDisbursementStatus::Sent) {
            throw new PayrollValidationException('Batch harus berstatus Sudah Dikirim dulu sebelum bisa ditandai Gagal.');
        }

        $batch->update([
            'status' => PayrollDisbursementStatus::Failed->value,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
            'failure_reason' => $reason,
        ]);

        return $batch->fresh();
    }

    /**
     * Generic CSV — satu format dipakai buat semua bank (upload manual ke
     * internet banking masing-masing). Di-generate on-the-fly dari data
     * snapshot item, bukan file fisik yang disimpan di disk.
     */
    public function toCsv(PayrollDisbursementBatch $batch): string
    {
        $batch->loadMissing(['items', 'payrollRun']);

        $rows = ['nama_penerima,nomor_rekening,nama_bank,jumlah,keterangan'];

        foreach ($batch->items as $item) {
            $keterangan = 'Gaji periode '.$batch->payrollRun->period_month.'/'.$batch->payrollRun->period_year;
            $rows[] = implode(',', [
                $this->csvField($item->account_holder_name),
                $this->csvField($item->account_number),
                $this->csvField($item->bank_name),
                number_format((float) $item->amount, 0, '', ''),
                $this->csvField($keterangan),
            ]);
        }

        return implode("\n", $rows);
    }

    private function csvField(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
