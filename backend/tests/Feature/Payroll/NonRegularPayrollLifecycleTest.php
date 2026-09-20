<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeAllowance\Enums\EmployeeAllowanceStatus;
use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lifecycle Non-Regular Payroll lewat HTTP — kalkulasi sesungguhnya sudah
 * dibuktikan di NonRegularPayrollCalculationTest, di sini fokus ke state
 * machine + yang paling kritis: isolasi antar run type saat Lock (supaya
 * Regular & Non-Regular yang jalan di periode sama tidak saling konsumsi
 * data satu sama lain).
 */
class NonRegularPayrollLifecycleTest extends TestCase
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
                            employeeId: $employee->id, grossEarning: '2000000.00', structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00', bpjsEmployeeTotal: '0.00', bpjsEmployerTotal: '0.00',
                            taxAmount: '0.00', loanDeductionTotal: '0.00', netPay: '2000000.00', lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function createRun(Employee $employee, string $type, int $month = 5): PayrollRun
    {
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => $type, 'period_year' => 2026, 'period_month' => $month,
            'employee_ids' => [$employee->id],
        ]);
        $response->assertCreated();

        return PayrollRun::findOrFail($response->json('data.id'));
    }

    private function fullLockFlow(PayrollRun $run): void
    {
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();
    }

    // 9 (di level Lock). Ready input jadi processed & terikat ke run yang
    // memprosesnya, tidak bisa diproses lagi run lain.
    public function test_lock_marks_ready_input_as_processed_and_links_run(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $component = NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'BONUS', 'name' => 'Bonus',
            'category' => 'bonus', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
        $input = EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $component->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '2000000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);

        $run = $this->createRun($employee, 'non_regular');
        $this->fullLockFlow($run);

        $input->refresh();
        $this->assertEquals(EmployeeNonRegularInputStatus::Processed, $input->status);
        $this->assertEquals($run->id, $input->payroll_run_id);
        $this->assertNotNull($input->processed_at);
    }

    // Isolasi kritis: Lock Non-Regular run TIDAK BOLEH menyentuh
    // EmployeeAllowance 'ready' milik Regular Payroll periode yang sama.
    public function test_locking_non_regular_run_does_not_consume_regular_employee_allowance(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $salaryComponent = SalaryComponent::create([
            'company_id' => $this->company->id, 'code' => 'TUNJ', 'name' => 'Tunjangan',
            'category' => 'allowance', 'is_addition' => true, 'calculation_method' => 'fixed',
            'amount' => '500000.00', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
        $allowance = EmployeeAllowance::create([
            'employee_id' => $employee->id, 'salary_component_id' => $salaryComponent->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '500000.00',
            'status' => 'ready',
        ]);

        $nonRegularRun = $this->createRun($employee, 'non_regular');
        $this->fullLockFlow($nonRegularRun);

        $allowance->refresh();
        $this->assertEquals(EmployeeAllowanceStatus::Ready, $allowance->status, 'EmployeeAllowance milik Regular tidak boleh ikut ke-konsumsi oleh Lock Non-Regular run.');
    }

    // Isolasi kritis kebalikannya: Lock Regular run TIDAK BOLEH menyentuh
    // EmployeeNonRegularInput 'ready' periode yang sama.
    public function test_locking_regular_run_does_not_consume_non_regular_input(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $component = NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'BONUS2', 'name' => 'Bonus',
            'category' => 'bonus', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
        $input = EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $component->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '2000000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);

        $regularRun = $this->createRun($employee, 'regular');
        $this->fullLockFlow($regularRun);

        $input->refresh();
        $this->assertEquals(EmployeeNonRegularInputStatus::Ready, $input->status, 'EmployeeNonRegularInput tidak boleh ikut ke-konsumsi oleh Lock Regular run.');
    }

    // 11. Locked non-regular run tidak bisa direcalculate.
    public function test_locked_non_regular_run_cannot_be_recalculated(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->createRun($employee, 'non_regular');
        $this->fullLockFlow($run);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertStatus(422);
    }

    // 12. Publish non-regular payroll bekerja.
    public function test_non_regular_run_can_be_published_after_lock(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->createRun($employee, 'non_regular');
        $this->fullLockFlow($run);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/publish")->assertOk();

        $this->assertNotNull($run->fresh()->published_at);
    }
}
