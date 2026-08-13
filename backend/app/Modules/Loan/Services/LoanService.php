<?php

namespace App\Modules\Loan\Services;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Enums\LoanInterestType;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Exceptions\LoanValidationException;
use App\Modules\Loan\Models\Loan;
use App\Modules\Loan\Models\LoanInstallment;
use App\Modules\Loan\Models\LoanSettlement;
use App\Modules\Loan\Support\LoanMath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
 
class LoanService
{
    public function __construct(private LoanApprovalService $approvalService)
    {
    }
 
    /**
     * @param array{principal: string|float, interest_rate?: string|float|null, interest_type?: string|null,
     *              tenor: int, first_deduction_date: string, purpose?: ?string} $data
     */
    public function create(Employee $employee, array $data, ?User $actor): Loan
    {
        $tenor = (int) $data['tenor'];
 
        if ($tenor < 1) {
            throw new LoanValidationException('Tenor minimal 1 kali cicilan.');
        }
 
        $interestType = $this->resolveInterestType($data['interest_type'] ?? null);
 
        $firstDeductionDate = Carbon::parse($data['first_deduction_date']);
        $plan = $this->calculateInstallmentPlan(
            (string) $data['principal'],
            $this->nullableToString($data['interest_rate'] ?? null),
            $tenor,
            $interestType,
        );
 
        return Loan::create([
            'employee_id' => $employee->id,
            'principal' => $data['principal'],
            'interest_rate' => $data['interest_rate'] ?? null,
            'interest_type' => $interestType->value,
            'tenor' => $tenor,
            'installment_amount' => $plan['installment_amount'],
            'total_repayment' => $plan['total_repayment'],
            'first_deduction_period_year' => $firstDeductionDate->year,
            'first_deduction_period_month' => $firstDeductionDate->month,
            'purpose' => $data['purpose'] ?? null,
            'status' => LoanStatus::Draft->value,
            'created_by_user_id' => $actor?->id,
        ]);
    }
 
    public function update(Loan $loan, array $data): Loan
    {
        if (! $loan->isEditable()) {
            throw new LoanValidationException('Loan yang sudah disubmit tidak dapat diubah. Batalkan lalu buat baru bila perlu.');
        }
 
        $principal = (string) ($data['principal'] ?? $loan->principal);
        $interestRate = array_key_exists('interest_rate', $data) ? $data['interest_rate'] : $loan->interest_rate;
        $interestType = array_key_exists('interest_type', $data)
            ? $this->resolveInterestType($data['interest_type'])
            : $loan->interest_type;
        $tenor = (int) ($data['tenor'] ?? $loan->tenor);
 
        if ($tenor < 1) {
            throw new LoanValidationException('Tenor minimal 1 kali cicilan.');
        }
 
        $firstDeductionDate = isset($data['first_deduction_date'])
            ? Carbon::parse($data['first_deduction_date'])
            : Carbon::createFromDate((int) $loan->first_deduction_period_year, (int) $loan->first_deduction_period_month, 1);
 
        $plan = $this->calculateInstallmentPlan($principal, $this->nullableToString($interestRate), $tenor, $interestType);
 
        $loan->update([
            'principal' => $principal,
            'interest_rate' => $interestRate,
            'interest_type' => $interestType instanceof LoanInterestType ? $interestType->value : $interestType,
            'tenor' => $tenor,
            'installment_amount' => $plan['installment_amount'],
            'total_repayment' => $plan['total_repayment'],
            'first_deduction_period_year' => $firstDeductionDate->year,
            'first_deduction_period_month' => $firstDeductionDate->month,
            'purpose' => $data['purpose'] ?? $loan->purpose,
        ]);
 
        return $loan->fresh();
    }
 
    public function submit(Loan $loan): Loan
    {
        if ($loan->status !== LoanStatus::Draft) {
            throw new LoanValidationException('Hanya loan berstatus Draft yang bisa disubmit.');
        }
 
        $loan->update([
            'status' => LoanStatus::Pending->value,
            'requested_at' => now(),
        ]);
 
        $this->approvalService->initiate($loan);
 
        return $loan->fresh();
    }
 
    public function disburse(Loan $loan): Loan
    {
        if ($loan->status !== LoanStatus::Approved) {
            throw new LoanValidationException('Hanya loan berstatus Approved yang bisa dicairkan.');
        }
 
        $this->generateInstallments($loan);
 
        $loan->update([
            'status' => LoanStatus::Active->value,
            'disbursed_at' => now(),
        ]);
 
        return $loan->fresh();
    }
 
    public function cancel(Loan $loan, string $reason): Loan
    {
        if (! in_array($loan->status, [LoanStatus::Draft, LoanStatus::Pending, LoanStatus::Approved], true)) {
            throw new LoanValidationException('Loan berstatus Active/Completed/Cancelled tidak bisa dibatalkan lewat aksi ini.');
        }
 
        $this->approvalService->cancelApprovalIfAny($loan);
 
        $loan->update([
            'status' => LoanStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
 
        return $loan->fresh();
    }
 
    /**
     * Hook untuk Payroll Generator — dipanggil setelah cicilan ini beneran
     * dipotong dari payroll run (lihat PayrollRunService::lock()). Loan
     * otomatis Completed begitu tidak ada installment Scheduled yang
     * tersisa — KECUALI loan ini punya LoanSettlement (final settlement
     * resignation), di situ statusnya Settled sebagai gantinya supaya
     * jelas kelunasannya lewat jalur settlement, bukan lifecycle normal.
     */
    public function markInstallmentPaid(LoanInstallment $installment, ?int $employeeDeductionId = null): LoanInstallment
    {
        if ($installment->status !== LoanInstallmentStatus::Scheduled) {
            throw new LoanValidationException('Installment ini bukan berstatus Scheduled.');
        }
 
        $installment->update([
            'status' => LoanInstallmentStatus::Paid->value,
            'paid_at' => now(),
            'employee_deduction_id' => $employeeDeductionId,
        ]);
 
        $loan = $installment->loan;
        $stillOutstanding = $loan->installments()->where('status', LoanInstallmentStatus::Scheduled->value)->exists();
 
        if (! $stillOutstanding && $loan->status === LoanStatus::Active) {
            $hasSettlement = $loan->settlement()->exists();
 
            $loan->update([
                'status' => $hasSettlement ? LoanStatus::Settled->value : LoanStatus::Completed->value,
                'completed_at' => now(),
            ]);
        }
 
        return $installment->fresh();
    }
 
    /**
     * Business action EKSPLISIT (harus dipanggil manual oleh Finance/HR,
     * TIDAK otomatis begitu resign_date terisi) untuk employee yang resign
     * dengan outstanding loan. Alur:
     *
     *   1. Employee harus sudah punya resign_date (Employment Lifecycle).
     *   2. Final payroll period = bulan resign_date.
     *   3. Semua installment Scheduled DI final period tersebut dan
     *      seterusnya (>= final period) di-supersede jadi Skipped —
     *      histori TETAP ADA, tidak dihapus — lalu digantikan SATU baris
     *      installment lump-sum baru di final period sebesar outstanding
     *      principal.
     *   4. LoanSettlement dibuat sebagai audit trail eksplisit: siapa yang
     *      trigger, kapan, berapa outstanding yang di-settle.
     *   5. Loan TETAP Active sampai Payroll beneran memproses/mengunci
     *      baris lump-sum itu lewat markInstallmentPaid() (dipanggil dari
     *      PayrollRunService::lock() seperti installment biasa) — baru di
     *      situ status jadi Settled. Payroll tidak menghitung ulang
     *      apa pun di sini, cuma mengonsumsi baris installment seperti biasa.
     *
     * [Inferensi]: bunga periode-periode yang belum terjadi (setelah final
     * period) di-waive — cuma outstanding principal yang di-settle.
     * Dokumentasi Talenta tidak menjelaskan detail rumus ini; ini desain
     * yang paling konsisten dengan method outstandingPrincipal() yang
     * sudah ada sebelumnya di model Loan, BUKAN klaim behavior Talenta.
     */
    public function settleForResignation(Loan $loan, ?User $actor): Loan
    {
        if ($loan->status !== LoanStatus::Active) {
            throw new LoanValidationException('Hanya loan berstatus Active yang bisa dibuatkan final settlement.');
        }
 
        $employee = $loan->employee;
 
        if (! $employee->resign_date) {
            throw new LoanValidationException('Employee belum memiliki resign date, final settlement tidak bisa dibuat.');
        }
 
        if ($loan->settlement()->exists()) {
            throw new LoanValidationException('Loan ini sudah memiliki final settlement.');
        }
 
        $resignDate = $employee->resign_date;
        $finalYear = $resignDate->year;
        $finalMonth = $resignDate->month;
        $finalPeriodValue = $finalYear * 12 + $finalMonth;
 
        return DB::transaction(function () use ($loan, $employee, $actor, $resignDate, $finalYear, $finalMonth, $finalPeriodValue) {
            $supersededInstallments = $loan->installments()
                ->where('status', LoanInstallmentStatus::Scheduled->value)
                ->get()
                ->filter(fn (LoanInstallment $i) => ((int) $i->payroll_period_year * 12 + (int) $i->payroll_period_month) >= $finalPeriodValue);
 
            if ($supersededInstallments->isEmpty()) {
                throw new LoanValidationException('Tidak ada outstanding installment pada/ setelah final period — tidak ada yang perlu di-settle.');
            }
 
            $outstandingPrincipal = $loan->outstandingPrincipal();
 
            if (! LoanMath::gte($outstandingPrincipal, '0.01')) {
                throw new LoanValidationException('Outstanding principal loan ini sudah 0, tidak ada yang perlu di-settle.');
            }
 
            $settlement = LoanSettlement::create([
                'loan_id' => $loan->id,
                'employee_id' => $employee->id,
                'resign_date' => $resignDate,
                'final_payroll_period_year' => $finalYear,
                'final_payroll_period_month' => $finalMonth,
                'outstanding_principal_settled' => $outstandingPrincipal,
                'superseded_installment_count' => $supersededInstallments->count(),
                'initiated_by_user_id' => $actor?->id,
            ]);
 
            foreach ($supersededInstallments as $installment) {
                $installment->update([
                    'status' => LoanInstallmentStatus::Skipped->value,
                    'loan_settlement_id' => $settlement->id,
                    'note' => trim(($installment->note ? $installment->note.' ' : '').'Digantikan final settlement resignation #'.$settlement->id.'.'),
                ]);
            }
 
            $nextInstallmentNumber = ((int) $loan->installments()->max('installment_number')) + 1;
 
            LoanInstallment::create([
                'loan_id' => $loan->id,
                'installment_number' => $nextInstallmentNumber,
                'payroll_period_year' => $finalYear,
                'payroll_period_month' => $finalMonth,
                'principal_portion' => $outstandingPrincipal,
                'interest_portion' => '0.00',
                'original_amount' => $outstandingPrincipal,
                'amount' => $outstandingPrincipal,
                'status' => LoanInstallmentStatus::Scheduled->value,
                'loan_settlement_id' => $settlement->id,
                'note' => 'Final settlement resignation #'.$settlement->id.'.',
            ]);
 
            return $loan->fresh();
        });
    }
 
    /**
     * @return array{rows: array<int, array{installment_number:int, principal_portion:string, interest_portion:string, amount:string}>,
     *               installment_amount: string, total_repayment: string}
     */
    public function calculateInstallmentPlan(
        string $principal,
        ?string $interestRate,
        int $tenor,
        string|LoanInterestType|null $interestType = null,
    ): array {
        $interestType = $this->resolveInterestType($interestType);
 
        return match ($interestType) {
            // None: bunga dipaksa 0 apa pun interest_rate yang dikirim —
            // sesuai requirement "None menghasilkan bunga 0".
            LoanInterestType::None => $this->calculateFlatPlan($principal, '0', $tenor),
            // Flat: TIDAK DIUBAH SAMA SEKALI dari behavior sebelumnya —
            // backward-compatible untuk loan existing.
            LoanInterestType::Flat => $this->calculateFlatPlan($principal, $interestRate ?? '0', $tenor),
            LoanInterestType::Declining => $this->calculateDecliningPlan($principal, $interestRate ?? '0', $tenor),
        };
    }
 
    private function resolveInterestType(string|LoanInterestType|null $interestType): LoanInterestType
    {
        if ($interestType instanceof LoanInterestType) {
            return $interestType;
        }
 
        // Default Flat kalau tidak dikirim sama sekali — persis behavior lama
        // sebelum interest_type ada, jadi caller lama tetap jalan sama persis.
        return LoanInterestType::tryFrom((string) $interestType) ?? LoanInterestType::Flat;
    }
 
    /**
     * Flat: total bunga dihitung SEKALI di muka dari principal awal
     * (interest_rate x principal), lalu dibagi rata ke tiap periode. Tidak
     * diubah dari implementasi original.
     */
    private function calculateFlatPlan(string $principal, string $interestRate, int $tenor): array
    {
        $rateFraction = LoanMath::div($interestRate, '100', 6);
        $totalInterest = LoanMath::mul($principal, $rateFraction);
        $totalRepayment = LoanMath::add($principal, $totalInterest);
 
        $principalPerPeriod = LoanMath::div($principal, (string) $tenor, 2);
        $amountPerPeriod = LoanMath::div($totalRepayment, (string) $tenor, 2);
 
        $rows = [];
        $principalAccumulated = '0.00';
        $amountAccumulated = '0.00';
 
        for ($i = 1; $i <= $tenor; $i++) {
            if ($i < $tenor) {
                $principalPortion = $principalPerPeriod;
                $amount = $amountPerPeriod;
            } else {
                $principalPortion = LoanMath::sub($principal, $principalAccumulated);
                $amount = LoanMath::sub($totalRepayment, $amountAccumulated);
            }
 
            $rows[] = [
                'installment_number' => $i,
                'principal_portion' => $principalPortion,
                'interest_portion' => LoanMath::sub($amount, $principalPortion),
                'amount' => $amount,
            ];
 
            $principalAccumulated = LoanMath::add($principalAccumulated, $principalPortion);
            $amountAccumulated = LoanMath::add($amountAccumulated, $amount);
        }
 
        return [
            'rows' => $rows,
            'installment_amount' => $amountPerPeriod,
            'total_repayment' => $totalRepayment,
        ];
    }
 
    /**
     * Declining balance: principal dibagi rata tiap periode (equal
     * principal), tapi bunga tiap periode dihitung dari OUTSTANDING
     * principal di awal periode itu (bukan dari principal awal kayak
     * Flat) — jadi nominal cicilan menurun tiap periode seiring
     * outstanding mengecil. installment_amount yang disimpan di kolom
     * Loan adalah RATA-RATA (total_repayment / tenor) karena nominal
     * per-periode tidak seragam — dipakai buat tampilan ringkasan saja,
     * breakdown sebenarnya ada di installment rows.
     */
    private function calculateDecliningPlan(string $principal, string $interestRate, int $tenor): array
    {
        $rateFraction = LoanMath::div($interestRate, '100', 6);
        $principalPerPeriod = LoanMath::div($principal, (string) $tenor, 2);
 
        $rows = [];
        $outstanding = $principal;
        $principalAccumulated = '0.00';
        $totalInterest = '0.00';
 
        for ($i = 1; $i <= $tenor; $i++) {
            $principalPortion = $i < $tenor
                ? $principalPerPeriod
                : LoanMath::sub($principal, $principalAccumulated);
 
            $interestPortion = LoanMath::mul($outstanding, $rateFraction);
            $amount = LoanMath::add($principalPortion, $interestPortion);
 
            $rows[] = [
                'installment_number' => $i,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'amount' => $amount,
            ];
 
            $outstanding = LoanMath::sub($outstanding, $principalPortion);
            $principalAccumulated = LoanMath::add($principalAccumulated, $principalPortion);
            $totalInterest = LoanMath::add($totalInterest, $interestPortion);
        }
 
        $totalRepayment = LoanMath::add($principal, $totalInterest);
 
        return [
            'rows' => $rows,
            'installment_amount' => LoanMath::div($totalRepayment, (string) $tenor, 2),
            'total_repayment' => $totalRepayment,
        ];
    }
 
    private function generateInstallments(Loan $loan): void
    {
        $plan = $this->calculateInstallmentPlan(
            (string) $loan->principal,
            $this->nullableToString($loan->interest_rate),
            $loan->tenor,
            $loan->interest_type,
        );
 
        $year = (int) $loan->first_deduction_period_year;
        $month = (int) $loan->first_deduction_period_month;
 
        foreach ($plan['rows'] as $row) {
            LoanInstallment::create([
                'loan_id' => $loan->id,
                'installment_number' => $row['installment_number'],
                'payroll_period_year' => $year,
                'payroll_period_month' => $month,
                'principal_portion' => $row['principal_portion'],
                'interest_portion' => $row['interest_portion'],
                'original_amount' => $row['amount'],
                'amount' => $row['amount'],
                'status' => LoanInstallmentStatus::Scheduled->value,
            ]);
 
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }
    }
 
    private function nullableToString(string|int|float|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}