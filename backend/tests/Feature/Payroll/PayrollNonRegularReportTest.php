<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollNonRegularReportTest extends TestCase
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

    // 14. Non-Regular Report menghasilkan data yang benar.
    public function test_non_regular_detail_report_returns_correct_component_row(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $component = NonRegularPayrollComponent::create([
            'company_id' => $this->company->id, 'code' => 'BONUS', 'name' => 'Bonus Kinerja',
            'category' => 'bonus', 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'non_regular', 'period_year' => 2026, 'period_month' => 5,
            'employee_ids' => [$employee->id],
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));

        $input = EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $component->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '2000000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        $report = $this->actingAs($this->admin)->getJson('/api/payroll-reports/non-regular/detail?'.http_build_query([
            'payroll_run_id' => $run->id,
        ]));

        $report->assertOk();
        $rows = $report->json('data');
        $this->assertCount(1, $rows);
        $this->assertEquals('Bonus Kinerja', $rows[0]['component_name']);
        $this->assertEquals('bonus', $rows[0]['category']);
        $this->assertEquals('2000000.00', $rows[0]['amount']);
        $this->assertTrue($rows[0]['is_addition']);
        $this->assertTrue($rows[0]['is_taxable']);
        $this->assertEquals('2000000.00', $rows[0]['net_pay']);
        $this->assertEquals('processed', $rows[0]['status']);

        $input->refresh();
        $this->assertEquals(EmployeeNonRegularInputStatus::Processed, $input->status);
    }

    public function test_non_regular_report_requires_view_non_regular_payroll_permission(): void
    {
        $userNoPermission = User::factory()->create();

        $this->actingAs($userNoPermission)
            ->getJson('/api/payroll-reports/non-regular/detail?period_year=2026&period_month=5')
            ->assertForbidden();
    }
}
