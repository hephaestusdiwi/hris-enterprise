<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Enums\PayrollDisbursementStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\CompanyBankSetting;
use App\Modules\Payroll\Models\PayrollDisbursementBatch;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDisbursementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $drafts = [];
                    foreach ($run->participants as $e) {
                        $drafts[$e->id] = new EmployeePayslipDraft(
                            employeeId: $e->id, grossEarning: '5000000.00', structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00', bpjsEmployeeTotal: '100000.00', bpjsEmployerTotal: '200000.00',
                            taxAmount: '50000.00', loanDeductionTotal: '0.00', netPay: '4850000.00', lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function setBankSetting(): void
    {
        $this->actingAs($this->admin)->putJson('/api/payroll-bank-setting', [
            'company_id' => $this->company->id,
            'bank_name' => 'Bank Contoh',
            'account_number' => '1234567890',
            'account_holder_name' => 'PT Contoh Sejahtera',
        ])->assertOk();
    }

    private function makeLockedRun(bool $withEmployeeBank = true): PayrollRun
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'bank_name' => $withEmployeeBank ? 'Bank Karyawan' : null,
            'bank_account_number' => $withEmployeeBank ? '999888777' : null,
            'bank_account_holder_name' => $withEmployeeBank ? 'Karyawan Contoh' : null,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk(); // auto-approve (no flow)
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        return $run->fresh();
    }

    // ---------- Company Bank Setting ----------

    public function test_bank_setting_can_be_saved_and_read(): void
    {
        $this->setBankSetting();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-bank-setting?company_id='.$this->company->id)
            ->assertOk();

        $this->assertEquals('Bank Contoh', $response->json('data.bank_name'));
        $this->assertEquals(1, CompanyBankSetting::where('company_id', $this->company->id)->count());
    }

    public function test_bank_setting_upsert_does_not_create_duplicate(): void
    {
        $this->setBankSetting();
        $this->setBankSetting(); // panggil lagi, harus update bukan bikin baru

        $this->assertEquals(1, CompanyBankSetting::where('company_id', $this->company->id)->count());
    }

    // ---------- Generate Batch ----------

    public function test_cannot_generate_without_bank_setting(): void
    {
        $run = $this->makeLockedRun();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/disbursements")
            ->assertStatus(422);
    }

    public function test_cannot_generate_when_run_not_locked(): void
    {
        $this->setBankSetting();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'bank_name' => 'X', 'bank_account_number' => '1', 'bank_account_holder_name' => 'X']);
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6, 'employee_ids' => [$employee->id],
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        // status masih Processed, belum Locked

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/disbursements")
            ->assertStatus(422);
    }

    public function test_cannot_generate_when_employee_missing_bank_data(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun(withEmployeeBank: false);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/disbursements")
            ->assertStatus(422);

        $this->assertStringContainsString('rekening bank', $response->json('message'));
        $this->assertEquals(0, PayrollDisbursementBatch::count());
    }

    public function test_generate_creates_batch_with_correct_snapshot(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/disbursements")
            ->assertCreated();

        $this->assertEquals(PayrollDisbursementStatus::Generated->value, $response->json('data.status'));
        $this->assertEquals('4850000.00', $response->json('data.total_amount'));
        $this->assertEquals(1, $response->json('data.total_employee_count'));
        $this->assertEquals(1, $response->json('data.items.0.employee_id') > 0 ? 1 : 0);

        $batch = PayrollDisbursementBatch::first();
        $this->assertEquals($run->currentRevision->id, $batch->payroll_run_revision_id);
        $this->assertCount(1, $batch->items);
        $this->assertEquals('999888777', $batch->items->first()->account_number);
        $this->assertEquals('4850000.00', $batch->items->first()->amount);
    }

    public function test_generate_twice_creates_two_separate_batches(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->assertCreated();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->assertCreated();

        $this->assertEquals(2, PayrollDisbursementBatch::where('payroll_run_id', $run->id)->count());
    }

    public function test_disbursement_snapshot_survives_employee_bank_data_change(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $response = $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->assertCreated();
        $batch = PayrollDisbursementBatch::findOrFail($response->json('data.id'));
        $item = $batch->items->first();

        // Employee ganti rekening SETELAH batch digenerate.
        $item->employee->update(['bank_account_number' => '111222333']);

        $item->refresh();
        $this->assertEquals('999888777', $item->account_number, 'Snapshot batch tidak boleh ikut berubah walau data employee berubah belakangan.');
    }

    // ---------- CSV Download ----------

    public function test_csv_download_contains_expected_columns_and_data(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $batchId = $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->json('data.id');

        $response = $this->actingAs($this->admin)->get("/api/disbursements/{$batchId}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->getContent();
        $this->assertStringContainsString('nama_penerima,nomor_rekening,nama_bank,jumlah,keterangan', $csv);
        $this->assertStringContainsString('999888777', $csv);
        $this->assertStringContainsString('4850000', $csv);
    }

    // ---------- Status transitions ----------

    public function test_mark_sent_then_confirmed_flow(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $batchId = $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/disbursements/{$batchId}/mark-sent")->assertOk();
        $this->assertEquals(PayrollDisbursementStatus::Sent, PayrollDisbursementBatch::find($batchId)->status);

        $this->actingAs($this->admin)->postJson("/api/disbursements/{$batchId}/mark-confirmed")->assertOk();
        $this->assertEquals(PayrollDisbursementStatus::Confirmed, PayrollDisbursementBatch::find($batchId)->status);
    }

    public function test_mark_failed_requires_reason_and_sent_status(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $batchId = $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->json('data.id');

        // Belum Sent -> mark-failed harus ditolak.
        $this->actingAs($this->admin)->postJson("/api/disbursements/{$batchId}/mark-failed", ['reason' => 'saldo kurang'])->assertStatus(422);

        $this->actingAs($this->admin)->postJson("/api/disbursements/{$batchId}/mark-sent")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/disbursements/{$batchId}/mark-failed", ['reason' => 'saldo kurang'])->assertOk();

        $batch = PayrollDisbursementBatch::find($batchId);
        $this->assertEquals(PayrollDisbursementStatus::Failed, $batch->status);
        $this->assertEquals('saldo kurang', $batch->failure_reason);
    }

    public function test_cannot_mark_confirmed_before_sent(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $batchId = $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/disbursements/{$batchId}/mark-confirmed")->assertStatus(422);
    }

    public function test_history_list_returns_all_batches_newest_first(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->assertCreated();
        $secondId = $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/disbursements")->json('data.id');

        $response = $this->actingAs($this->admin)->getJson("/api/payroll-runs/{$run->id}/disbursements")->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals($secondId, $response->json('data.0.id'), 'Batch terbaru harus di urutan pertama.');
    }

    // ---------- Authorization ----------

    public function test_unauthorized_user_cannot_generate_disbursement(): void
    {
        $this->setBankSetting();
        $run = $this->makeLockedRun();
        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->postJson("/api/payroll-runs/{$run->id}/disbursements")
            ->assertForbidden();
    }
}
