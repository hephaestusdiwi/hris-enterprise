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
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Lifecycle end-to-end THR Payroll lewat HTTP — mirror persis pola
 * PayrollApprovalTest (top-level PayrollCalculationEngineInterface di-stub,
 * kalkulasi sesungguhnya sudah dibuktikan benar di ThrPayrollCalculationTest).
 * Fokus di sini murni: apakah type=thr bisa lewat SELURUH state machine yang
 * SAMA dengan Regular (create->proceed->approval->lock->publish->ESS akses)
 * tanpa endpoint/kode baru.
 */
class ThrPayrollLifecycleTest extends TestCase
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
                    foreach ($run->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id, grossEarning: '3000000.00', structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00', bpjsEmployeeTotal: '0.00', bpjsEmployerTotal: '0.00',
                            taxAmount: '50000.00', loanDeductionTotal: '0.00', netPay: '2950000.00', lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function makeRoleFlow(): array
    {
        $role = Role::firstOrCreate(['name' => 'finance-approver', 'guard_name' => 'web']);
        $flow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'Payroll Lock Approval',
            'code' => 'payroll-lock-'.uniqid(), 'approval_type' => 'payroll', 'is_active' => true,
        ]);
        ApprovalStep::create([
            'approval_flow_id' => $flow->id, 'sequence' => 1, 'name' => 'Finance Approval',
            'approver_type' => ApproverType::SpecificRole->value, 'approver_role_id' => $role->id, 'is_active' => true,
        ]);

        return [$flow, $role];
    }

    // 2. THR payroll dapat dibuat (via endpoint generik /payroll-runs + type=thr).
    public function test_thr_run_can_be_created_and_processed(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 4,
            'employee_ids' => [$employee->id],
        ]);

        $response->assertCreated();
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->assertEquals('thr', $run->type->value);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $this->assertDatabaseHas('payslips', ['employee_id' => $employee->id, 'payroll_run_id' => $run->id, 'net_pay' => '2950000.00']);
    }

    // 10, 11, 12. Approval flow, Lock mencegah perubahan, Publish bekerja — sama seperti Regular.
    public function test_thr_run_follows_full_approval_lock_publish_flow(): void
    {
        [, $role] = $this->makeRoleFlow();
        $approver = User::factory()->create();
        $approver->assignRole($role);

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = PayrollRun::findOrFail(
            $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
                'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 4,
                'employee_ids' => [$employee->id],
            ])->json('data.id')
        );
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $decision = $run->fresh()->approvalRequest->stepDecisions()->first();
        $this->actingAs($approver)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();
        $run->refresh();
        $this->assertEquals('locked', $run->status->value);

        // Locked -> tidak bisa direcalculate (mencegah perubahan).
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertStatus(422);

        // Publish.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/publish")->assertOk();
        $run->refresh();
        $this->assertNotNull($run->published_at);
    }

    // 13. THR payslip dapat diakses employee via ESS (endpoint /my-payslips existing, reuse total).
    public function test_employee_can_access_own_thr_payslip_after_publish(): void
    {
        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUser->id]);

        $run = PayrollRun::findOrFail(
            $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
                'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 4,
                'employee_ids' => [$employee->id],
            ])->json('data.id')
        );
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/publish")->assertOk();

        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->firstOrFail();

        $response = $this->actingAs($employeeUser)->get("/api/my-payslips/{$payslip->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    // Regular & THR run harus bisa jalan bersamaan di periode yang sama
    // (unique constraint sudah diupdate ikutkan kolom type).
    public function test_regular_and_thr_run_can_coexist_in_same_period(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 4,
            'employee_ids' => [$employee->id],
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 4,
            'employee_ids' => [$employee->id],
        ])->assertCreated();

        $this->assertEquals(2, PayrollRun::where('company_id', $this->company->id)->count());
    }

    // 1. Regular Payroll (default type, tanpa field 'type' sama sekali) tetap
    // bisa dibuat tanpa perubahan apa pun — backward compatibility.
    public function test_regular_payroll_without_type_field_still_defaults_correctly(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 7,
            'employee_ids' => [$employee->id],
        ]);

        $response->assertCreated();
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->assertEquals('regular', $run->type->value);
        $this->assertTrue($run->isRegular());
    }
}
