<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 15. Authorization/permission bekerja — membuktikan gating type-aware yang
 * di-layer TAMBAHAN di PayrollRunController::assertTypePermission() di atas
 * permission generik existing, dan membuktikan Regular Payroll SAMA SEKALI
 * TIDAK terdampak (backward compatibility RBAC).
 */
class PayrollTypeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->company = Company::factory()->create();

        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $drafts = [];
                    foreach ($run->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id, grossEarning: '1000000.00', structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00', bpjsEmployeeTotal: '0.00', bpjsEmployerTotal: '0.00',
                            taxAmount: '0.00', loanDeductionTotal: '0.00', netPay: '1000000.00', lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'custom-'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user->assignRole($role);

        return $user;
    }

    // Backward compatibility: permission generik SAJA (tanpa permission thr/
    // non-regular apa pun) tetap cukup buat Regular Payroll — persis sebelum Fase 7.
    public function test_regular_payroll_only_needs_generic_permissions(): void
    {
        $user = $this->userWithPermissions(['view payroll runs', 'create payroll runs', 'lock payroll runs', 'publish payroll runs']);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($user)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->assertCreated();
    }

    // 'create payroll runs' generik SAJA TIDAK CUKUP buat bikin THR run.
    public function test_generic_create_permission_alone_cannot_create_thr_run(): void
    {
        $user = $this->userWithPermissions(['create payroll runs']);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($user)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->assertForbidden();
    }

    public function test_create_thr_payroll_permission_together_with_generic_allows_creation(): void
    {
        $user = $this->userWithPermissions(['create payroll runs', 'create thr payroll']);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($user)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->assertCreated();
    }

    public function test_generic_create_permission_alone_cannot_create_non_regular_run(): void
    {
        $user = $this->userWithPermissions(['create payroll runs']);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($user)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'non_regular', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->assertForbidden();
    }

    public function test_view_thr_payroll_permission_required_to_view_thr_run(): void
    {
        $creator = $this->userWithPermissions(['create payroll runs', 'create thr payroll', 'view payroll runs', 'view thr payroll']);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $runId = $this->actingAs($creator)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->json('data.id');

        $viewerWithoutThr = $this->userWithPermissions(['view payroll runs']);
        $this->actingAs($viewerWithoutThr)->getJson("/api/payroll-runs/{$runId}")->assertForbidden();

        $viewerWithThr = $this->userWithPermissions(['view payroll runs', 'view thr payroll']);
        $this->actingAs($viewerWithThr)->getJson("/api/payroll-runs/{$runId}")->assertOk();
    }

    public function test_lock_thr_payroll_permission_required_to_lock_thr_run(): void
    {
        $creator = $this->userWithPermissions([
            'create payroll runs', 'create thr payroll', 'view payroll runs',
            'edit thr payroll', 'request payroll approval', 'request thr payroll approval',
        ]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $runId = $this->actingAs($creator)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->json('data.id');
        $this->actingAs($creator)->postJson("/api/payroll-runs/{$runId}/proceed-payslip")->assertOk();
        $this->actingAs($creator)->postJson("/api/payroll-runs/{$runId}/request-approval")->assertOk();

        // Ada company tanpa ApprovalFlow -> auto-resolved, langsung Approved.
        $lockerWithoutThr = $this->userWithPermissions(['lock payroll runs']);
        $this->actingAs($lockerWithoutThr)->postJson("/api/payroll-runs/{$runId}/lock")->assertForbidden();

        $lockerWithThr = $this->userWithPermissions(['lock payroll runs', 'lock thr payroll']);
        $this->actingAs($lockerWithThr)->postJson("/api/payroll-runs/{$runId}/lock")->assertOk();
    }

    public function test_admin_role_can_access_everything_thr_and_non_regular(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'non_regular', 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ])->assertCreated();
    }
}
