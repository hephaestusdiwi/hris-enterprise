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

class PayrollSalaryReportTest extends TestCase
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

        // Stub dengan PayslipLine eksplisit source=salary_structure (basic
        // salary) dan source=allowance (allowance) — dibutuhkan buat
        // memvalidasi derivasi Basic Salary/Allowance dari PayslipReportService.
        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $drafts = [];
                    foreach ($run->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id,
                            grossEarning: '6000000.00',
                            structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00',
                            bpjsEmployeeTotal: '100000.00',
                            bpjsEmployerTotal: '200000.00',
                            taxAmount: '50000.00',
                            loanDeductionTotal: '0.00',
                            netPay: '5650000.00',
                            lines: [
                                new PayslipLineDraft(
                                    type: PayslipLineType::Earning,
                                    source: PayslipLineSource::SalaryStructure,
                                    label: 'Gaji Pokok',
                                    amount: '5000000.00',
                                    referenceId: null,
                                ),
                                new PayslipLineDraft(
                                    type: PayslipLineType::Earning,
                                    source: PayslipLineSource::Allowance,
                                    label: 'Tunjangan Transport',
                                    amount: '1000000.00',
                                    referenceId: null,
                                ),
                            ],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function makeLockedRunWithEmployee(?Branch $branch = null, ?Department $department = null): array
    {
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch?->id,
            'department_id' => $department?->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        return [$run->fresh(), $employee];
    }

    /**
     * Beberapa test butuh 2+ employee di PERIODE YANG SAMA (buat komparasi
     * filter) — tidak bisa pakai 2 PayrollRun terpisah karena
     * unique(company_id, period_year, period_month) cuma izinkan SATU run
     * per company per periode. Jadi employee-employee itu harus masuk ke
     * SATU run yang sama.
     *
     * @param  Employee[]  $employees
     */
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

    // ---------- Basic Salary / Allowance derivation ----------

    public function test_salary_detail_correctly_derives_basic_salary_and_allowance(): void
    {
        [, $employee] = $this->makeLockedRunWithEmployee();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/salary/detail?period_year=2026&period_month=6')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('employee_id', $employee->id);
        $this->assertEquals('5000000.00', $row['basic_salary']);
        $this->assertEquals('1000000.00', $row['allowance_total']);
        $this->assertEquals('6000000.00', $row['gross_earning']);
        $this->assertEquals('5650000.00', $row['net_pay']);
    }

    // ---------- Filter: company / branch / department / employee ----------

    public function test_filter_by_branch(): void
    {
        $branchA = Branch::factory()->create(['company_id' => $this->company->id]);
        $branchB = Branch::factory()->create(['company_id' => $this->company->id]);
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branchA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branchB->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/salary/detail?period_year=2026&period_month=6&branch_id={$branchA->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('employee_id');
        $this->assertTrue($ids->contains($employeeA->id));
        $this->assertFalse($ids->contains($employeeB->id));
    }

    public function test_filter_by_department(): void
    {
        $deptA = Department::factory()->create(['company_id' => $this->company->id]);
        $deptB = Department::factory()->create(['company_id' => $this->company->id]);
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'department_id' => $deptA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'department_id' => $deptB->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/salary/detail?period_year=2026&period_month=6&department_id={$deptA->id}")
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
            ->getJson("/api/payroll-reports/salary/detail?period_year=2026&period_month=6&employee_id={$employeeA->id}")
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals($employeeA->id, $response->json('data.data.0.employee_id'));
    }

    public function test_filter_by_period_excludes_other_months(): void
    {
        [, $employee] = $this->makeLockedRunWithEmployee();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/salary/detail?period_year=2026&period_month=7')
            ->assertOk();

        $this->assertCount(0, $response->json('data.data'));
    }

    // ---------- Company isolation ----------

    public function test_company_isolation_does_not_leak_other_company_data(): void
    {
        [, $ownEmployee] = $this->makeLockedRunWithEmployee();

        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $otherRunResponse = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $otherCompany->id, 'period_year' => 2026, 'period_month' => 6, 'employee_ids' => [$otherEmployee->id],
        ]);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$otherRunResponse->json('data.id')}/proceed-payslip")->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/salary/detail?period_year=2026&period_month=6&company_id={$this->company->id}")
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

        $detail = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/salary/detail?period_year=2026&period_month=6')
            ->json('data.data');
        $summary = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/salary/summary?period_year=2026&period_month=6')
            ->json('data');

        $this->assertEquals(2, $summary['employee_count']);
        $this->assertEquals(
            collect($detail)->sum(fn ($r) => (float) $r['net_pay']),
            (float) $summary['net_pay']
        );
        $this->assertEquals(
            collect($detail)->sum(fn ($r) => (float) $r['basic_salary']),
            (float) $summary['basic_salary']
        );
    }

    // ---------- Revisi lama tidak ikut ke-hitung (immutability-consistent) ----------

    public function test_only_current_revision_counted_after_recalculate(): void
    {
        [$run, $employee] = $this->makeLockedRunWithEmployee();
        // Recalculate lagi -> revisi 2 jadi current, revisi 1 jadi historical.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/salary/detail?period_year=2026&period_month=6')
            ->assertOk();

        // Cuma 1 baris per employee (revisi current), bukan 2 (kalau revisi lama ikut ke-query, ini akan gagal).
        $rows = collect($response->json('data.data'))->where('employee_id', $employee->id);
        $this->assertCount(1, $rows);
    }

    // ---------- Excel export ----------

    public function test_salary_detail_excel_export_downloads_successfully(): void
    {
        $this->makeLockedRunWithEmployee();

        $response = $this->actingAs($this->admin)
            ->get('/api/payroll-reports/salary/detail/export/excel?period_year=2026&period_month=6');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_salary_summary_excel_export_downloads_successfully(): void
    {
        $this->makeLockedRunWithEmployee();

        $response = $this->actingAs($this->admin)
            ->get('/api/payroll-reports/salary/summary/export/excel?period_year=2026&period_month=6');

        $response->assertOk();
    }

    // ---------- PDF export (butuh barryvdh/laravel-dompdf ter-install) ----------

    public function test_salary_detail_pdf_export_downloads_successfully(): void
    {
        $this->makeLockedRunWithEmployee();

        $response = $this->actingAs($this->admin)
            ->get('/api/payroll-reports/salary/detail/export/pdf?period_year=2026&period_month=6');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    // ---------- Authorization ----------

    public function test_unauthorized_user_cannot_access_salary_report(): void
    {
        $this->makeLockedRunWithEmployee();
        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->getJson('/api/payroll-reports/salary/detail?period_year=2026&period_month=6')
            ->assertForbidden();
    }

    public function test_missing_required_filters_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/salary/detail')
            ->assertStatus(422);
    }
}
