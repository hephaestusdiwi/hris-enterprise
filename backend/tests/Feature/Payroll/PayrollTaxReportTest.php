<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\DataTransferObjects\PayslipLineDraft;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTaxReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;
    public bool $simulateAnnualReconciliation = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class($this) implements PayrollCalculationEngineInterface
            {
                public function __construct(private PayrollTaxReportTest $test)
                {
                }

                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $drafts = [];
                    foreach ($run->participants as $employee) {
                        $label = $this->test->simulateAnnualReconciliation ? 'PPh 21 (Rekonsiliasi Tahunan)' : 'PPh 21';
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id,
                            grossEarning: '6000000.00', structuralDeduction: '0.00', manualDeductionTotal: '0.00',
                            bpjsEmployeeTotal: '100000.00', bpjsEmployerTotal: '200000.00',
                            taxAmount: '150000.00', loanDeductionTotal: '0.00', netPay: '5750000.00',
                            lines: [
                                new PayslipLineDraft(PayslipLineType::Tax, PayslipLineSource::Pph21, $label, '150000.00', null),
                            ],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function makeLockedRunWithEmployees(array $employees): PayrollRun
    {
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => collect($employees)->pluck('id')->all(),
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        return $run->fresh();
    }

    // ---------- Detail dasar ----------

    public function test_tax_detail_returns_correct_amounts(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/tax/detail?period_year=2026&period_month=6')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('employee_id', $employee->id);
        $this->assertEquals('6000000.00', $row['gross_earning']);
        $this->assertEquals('150000.00', $row['tax_amount']);
        $this->assertFalse($row['is_annual_reconciliation']);
    }

    public function test_tax_detail_flags_annual_reconciliation_month(): void
    {
        $this->simulateAnnualReconciliation = true;
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/tax/detail?period_year=2026&period_month=6')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('employee_id', $employee->id);
        $this->assertTrue($row['is_annual_reconciliation']);
    }

    // ---------- Filter ----------

    public function test_filter_by_department(): void
    {
        $deptA = Department::factory()->create(['company_id' => $this->company->id]);
        $deptB = Department::factory()->create(['company_id' => $this->company->id]);
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'department_id' => $deptA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'department_id' => $deptB->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/tax/detail?period_year=2026&period_month=6&department_id={$deptA->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('employee_id');
        $this->assertTrue($ids->contains($employeeA->id));
        $this->assertFalse($ids->contains($employeeB->id));
    }

    public function test_filter_by_branch(): void
    {
        $branchA = Branch::factory()->create(['company_id' => $this->company->id]);
        $branchB = Branch::factory()->create(['company_id' => $this->company->id]);
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branchA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branchB->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/tax/detail?period_year=2026&period_month=6&branch_id={$branchA->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('employee_id');
        $this->assertTrue($ids->contains($employeeA->id));
        $this->assertFalse($ids->contains($employeeB->id));
    }

    public function test_filter_by_employee(): void
    {
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/tax/detail?period_year=2026&period_month=6&employee_id={$employeeA->id}")
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_company_isolation_does_not_leak_other_company_data(): void
    {
        $ownEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$ownEmployee]);

        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $otherRunResponse = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $otherCompany->id, 'period_year' => 2026, 'period_month' => 6, 'employee_ids' => [$otherEmployee->id],
        ]);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$otherRunResponse->json('data.id')}/proceed-payslip")->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/tax/detail?period_year=2026&period_month=6&company_id={$this->company->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('employee_id');
        $this->assertTrue($ids->contains($ownEmployee->id));
        $this->assertFalse($ids->contains($otherEmployee->id));
    }

    // ---------- Summary konsisten dengan SUM Detail ----------

    public function test_summary_totals_match_sum_of_detail_rows(): void
    {
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $summary = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/tax/summary?period_year=2026&period_month=6')
            ->json('data');

        $this->assertEquals(2, $summary['employee_count']);
        $this->assertEquals(300000, (float) $summary['tax_amount']);
    }

    // ---------- Revisi lama tidak ikut ke-hitung ----------

    public function test_only_current_revision_counted_after_recalculate(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->makeLockedRunWithEmployees([$employee]);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/tax/detail?period_year=2026&period_month=6')
            ->assertOk();

        $rows = collect($response->json('data.data'))->where('employee_id', $employee->id);
        $this->assertCount(1, $rows);
    }

    // ---------- Export ----------

    public function test_tax_detail_excel_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $this->actingAs($this->admin)
            ->get('/api/payroll-reports/tax/detail/export/excel?period_year=2026&period_month=6')
            ->assertOk();
    }

    public function test_tax_summary_excel_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $this->actingAs($this->admin)
            ->get('/api/payroll-reports/tax/summary/export/excel?period_year=2026&period_month=6')
            ->assertOk();
    }

    public function test_tax_detail_pdf_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->get('/api/payroll-reports/tax/detail/export/pdf?period_year=2026&period_month=6');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    // ---------- Authorization ----------

    public function test_unauthorized_user_cannot_access_tax_report(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);
        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->getJson('/api/payroll-reports/tax/detail?period_year=2026&period_month=6')
            ->assertForbidden();
    }

    public function test_missing_required_filters_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/tax/detail')
            ->assertStatus(422);
    }
}
