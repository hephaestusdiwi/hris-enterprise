<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AUDIT PAYROLL — REVISION IMMUTABILITY (HIGH finding) fix.
 *
 * File TERPISAH dari PayrollHistoryTest.php/PayrollApprovalTest.php dengan
 * SENGAJA — stub kalkulasi di sini REVISION-AWARE (nilai beda tiap revisi),
 * beda dari stub file lain yang nilainya konstan. Tidak menyentuh stub di
 * file lain sama sekali (blast radius murni file ini).
 */
class PayrollPayslipImmutabilityTest extends TestCase
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

        // Stub REVISION-AWARE — gross/net beda tiap revisi, biar test bisa
        // benar-benar membuktikan nilai revisi lama TIDAK ikut berubah saat
        // revisi baru dibuat (bukan cuma "row-nya masih ada").
        // calculateDraftsForRun() dipanggil SEBELUM revisionNumber dihitung
        // di PayrollRunService::proceedPayslip(), jadi current_revision+1 di
        // sini = nomor revisi yang SEDANG dibuat.
        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $revisionBeingCreated = $run->current_revision + 1;
                    $gross = 5000000 + (($revisionBeingCreated - 1) * 500000);
                    $net = 4500000 + (($revisionBeingCreated - 1) * 500000);

                    $drafts = [];
                    foreach ($run->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id,
                            grossEarning: number_format($gross, 2, '.', ''),
                            structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00',
                            bpjsEmployeeTotal: '100000.00',
                            bpjsEmployerTotal: '200000.00',
                            taxAmount: '50000.00',
                            loanDeductionTotal: '0.00',
                            netPay: number_format($net, 2, '.', ''),
                            lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function createRunWithEmployee(): array
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'status' => PayrollRunStatus::Draft->value,
        ]);
        $run->participants()->sync([$employee->id]);

        return [$run, $employee];
    }

    private function proceed(PayrollRun $run): void
    {
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
    }

    // ---------- TEST 1 & 2 — historical payslip tidak bisa publish/unpublish ----------

    public function test_historical_payslip_cannot_be_published(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run); // Revisi 1
        $oldPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();

        $this->proceed($run); // Revisi 2 -> Revisi 1 jadi historical

        $response = $this->actingAs($this->admin)->postJson("/api/payslips/{$oldPayslip->id}/publish");

        $response->assertStatus(422);
        $this->assertArrayHasKey('message', $response->json());
        $this->assertFalse($response->json('success'));

        $this->assertFalse($oldPayslip->fresh()->is_published);
    }

    public function test_historical_payslip_cannot_be_unpublished(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run); // Revisi 1
        $oldPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        // Set state awal is_published=true LANGSUNG di DB (bukan lewat endpoint,
        // karena tujuannya nguji unpublish, bukan publish) — biar ada sesuatu
        // yang "berpotensi berubah" kalau guard tidak jalan.
        $oldPayslip->update(['is_published' => true]);

        $this->proceed($run); // Revisi 2 -> Revisi 1 jadi historical

        $response = $this->actingAs($this->admin)->postJson("/api/payslips/{$oldPayslip->id}/unpublish");

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));

        // State TIDAK berubah — tetap true seperti sebelum percobaan unpublish.
        $this->assertTrue($oldPayslip->fresh()->is_published);
    }

    // ---------- TEST 3 & 4 — current revision tetap bisa publish/unpublish ----------

    public function test_current_revision_payslip_can_still_be_published(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run);
        $currentPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();

        $this->actingAs($this->admin)
            ->postJson("/api/payslips/{$currentPayslip->id}/publish")
            ->assertOk();

        $this->assertTrue($currentPayslip->fresh()->is_published);
    }

    public function test_current_revision_payslip_can_still_be_unpublished(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run);
        $currentPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $currentPayslip->update(['is_published' => true]);

        $this->actingAs($this->admin)
            ->postJson("/api/payslips/{$currentPayslip->id}/unpublish")
            ->assertOk();

        $this->assertFalse($currentPayslip->fresh()->is_published);
    }

    // ---------- CRITICAL — financial immutability lintas revisi ----------

    public function test_financial_values_remain_unchanged_across_revisions(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();

        $this->proceed($run); // Revisi 1: gross=5.000.000, net=4.500.000
        $payslip1 = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $capture1 = $payslip1->only(['gross_earning', 'structural_deduction', 'manual_deduction_total', 'bpjs_employee_total', 'bpjs_employer_total', 'tax_amount', 'loan_deduction_total', 'net_pay']);
        $this->assertEquals('5000000.00', $payslip1->gross_earning);
        $this->assertEquals('4500000.00', $payslip1->net_pay);

        $this->proceed($run); // Revisi 2: gross=5.500.000, net=5.000.000
        $run->refresh();
        $payslip1->refresh();
        $this->assertEquals($capture1, $payslip1->only(array_keys($capture1)), 'Revisi 1 TIDAK BOLEH berubah setelah Revisi 2 dibuat.');

        $payslip2 = Payslip::where('employee_id', $employee->id)->where('payroll_run_revision_id', $run->currentRevision->id)->first();
        $capture2 = $payslip2->only(['gross_earning', 'structural_deduction', 'manual_deduction_total', 'bpjs_employee_total', 'bpjs_employer_total', 'tax_amount', 'loan_deduction_total', 'net_pay']);
        $this->assertEquals('5500000.00', $payslip2->gross_earning);
        $this->assertEquals('5000000.00', $payslip2->net_pay);
        $this->assertNotEquals($capture1['gross_earning'], $capture2['gross_earning'], 'Revisi 1 dan 2 harus punya nilai BEDA (memvalidasi stub, bukan cuma sistem).');

        $this->proceed($run); // Revisi 3: gross=6.000.000, net=5.400.000
        $run->refresh();
        $payslip1->refresh();
        $payslip2->refresh();
        $this->assertEquals($capture1, $payslip1->only(array_keys($capture1)), 'Revisi 1 TIDAK BOLEH berubah setelah Revisi 3 dibuat.');
        $this->assertEquals($capture2, $payslip2->only(array_keys($capture2)), 'Revisi 2 TIDAK BOLEH berubah setelah Revisi 3 dibuat.');

        $payslip3 = Payslip::where('employee_id', $employee->id)->where('payroll_run_revision_id', $run->currentRevision->id)->first();
        $this->assertEquals('6000000.00', $payslip3->gross_earning);
        $this->assertEquals('5500000.00', $payslip3->net_pay);
    }

    // ---------- Approval cycle tidak mengubah historical revision ----------

    public function test_approval_cycle_does_not_mutate_historical_revision(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run); // Revisi 1
        $payslip1 = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $capture1 = $payslip1->only(['gross_earning', 'net_pay']);

        $this->proceed($run); // Revisi 2 (current)

        $role = Role::firstOrCreate(['name' => 'immutability-approver', 'guard_name' => 'web']);
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);
        $flow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'Immutability Flow', 'code' => 'immut-'.uniqid(),
            'approval_type' => 'payroll', 'is_active' => true,
        ]);
        ApprovalStep::create([
            'approval_flow_id' => $flow->id, 'sequence' => 1, 'name' => 'Step 1',
            'approver_type' => ApproverType::SpecificRole->value, 'approver_role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->fresh()->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])
            ->assertOk();

        $payslip1->refresh();
        $this->assertEquals($capture1, $payslip1->only(['gross_earning', 'net_pay']), 'Approval cycle tidak boleh mengubah nilai revisi lama.');
    }

    // ---------- Jalur resmi PayrollRunService::publish()/unpublish() tetap jalan ----------

    public function test_official_payroll_run_publish_flow_still_works_for_current_revision(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run);
        $this->makeApprovedAndLocked($run);

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/publish")
            ->assertOk();

        $currentPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $this->assertTrue($currentPayslip->is_published);

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/unpublish")
            ->assertOk();
        $this->assertFalse($currentPayslip->fresh()->is_published);
    }

    private function makeApprovedAndLocked(PayrollRun $run): void
    {
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        // Tanpa ApprovalFlow dikonfigurasi -> auto-approve (behavior existing, tidak diubah).
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();
    }

    // ---------- Authorization ----------

    public function test_unauthorized_user_cannot_access_publish_endpoint(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run);
        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();

        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->postJson("/api/payslips/{$payslip->id}/publish")
            ->assertForbidden();

        $this->actingAs($userWithoutPermission)
            ->postJson("/api/payslips/{$payslip->id}/unpublish")
            ->assertForbidden();
    }

    public function test_authorized_user_current_ok_but_historical_rejected(): void
    {
        [$run, $employee] = $this->createRunWithEmployee();
        $this->proceed($run); // Revisi 1
        $oldPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $this->proceed($run); // Revisi 2
        $currentPayslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_revision_id', $run->currentRevision->id)->first();

        // Authorized (admin, punya permission) — current payslip tetap sukses.
        $this->actingAs($this->admin)->postJson("/api/payslips/{$currentPayslip->id}/publish")->assertOk();

        // Authorized TAPI payslip historical — tetap ditolak (revision guard, bukan permission).
        $this->actingAs($this->admin)->postJson("/api/payslips/{$oldPayslip->id}/publish")->assertStatus(422);
    }
}
