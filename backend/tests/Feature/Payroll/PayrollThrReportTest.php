<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\DataTransferObjects\PayslipLineDraft;
use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;
use App\Modules\Payroll\Models\PayrollRun;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollThrReportTest extends TestCase
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
                            taxAmount: '150000.00', loanDeductionTotal: '0.00', netPay: '2850000.00',
                            lines: [new PayslipLineDraft(PayslipLineType::Earning, PayslipLineSource::Thr, 'THR', '3000000.00')],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    // 14. THR Report menghasilkan data yang benar.
    public function test_thr_detail_report_returns_correct_employee_row(): void
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'join_date' => Carbon::parse('2024-01-01'),
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'type' => 'thr', 'period_year' => 2026, 'period_month' => 4,
            'employee_ids' => [$employee->id],
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $report = $this->actingAs($this->admin)->getJson('/api/payroll-reports/thr/detail?'.http_build_query([
            'payroll_run_id' => $run->id,
        ]));

        $report->assertOk();
        $rows = $report->json('data');
        $this->assertCount(1, $rows);
        $this->assertEquals($employee->employee_number, $rows[0]['employee_number']);
        $this->assertEquals('3000000.00', $rows[0]['thr_amount']);
        $this->assertEquals('150000.00', $rows[0]['tax_amount']);
        $this->assertEquals('2850000.00', $rows[0]['net_pay']);
        $this->assertEquals('processed', $rows[0]['status']);
    }

    public function test_thr_report_requires_view_thr_payroll_permission(): void
    {
        $userNoPermission = User::factory()->create();

        $this->actingAs($userNoPermission)
            ->getJson('/api/payroll-reports/thr/detail?period_year=2026&period_month=4')
            ->assertForbidden();
    }
}
