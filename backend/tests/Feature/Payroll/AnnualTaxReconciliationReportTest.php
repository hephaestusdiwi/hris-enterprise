<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use App\Modules\Pph21\DataTransferObjects\AnnualReconciliationResult;
use App\Modules\Pph21\DataTransferObjects\MonthlyTaxResult;
use App\Modules\Pph21\Enums\TaxMethod;
use App\Modules\Pph21\Enums\TerCategory;
use App\Modules\SalaryComponent\Enums\SalaryComponentCategory;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualTaxReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->company = Company::factory()->create(['npwp' => '01.234.567.8-901.000']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->app->bind(EmployeeSalaryResolverInterface::class, function () {
            return new class implements EmployeeSalaryResolverInterface
            {
                public function resolveComponents($employee, $referenceDate): array
                {
                    $component = new SalaryComponent([
                        'name' => 'Gaji Pokok', 'category' => SalaryComponentCategory::BasicSalary->value,
                        'is_addition' => true, 'is_taxable' => true, 'include_in_bpjs_base' => true,
                    ]);

                    return [new ResolvedSalaryLine($component, '10000000.00', null, null, 'structure')];
                }

                public function resolveActiveVersion(\App\Modules\Employee\Models\Employee $employee, \Carbon\Carbon $referenceDate): ?\App\Modules\EmployeeSalary\Models\EmployeeSalary
                {
                    return null;
                }

                public function resolvePreview(\App\Modules\Employee\Models\Employee $employee, \Carbon\Carbon $referenceDate, string $salaryStructureCode, \Illuminate\Support\Collection $draftOverrides): array
                {
                    return [];
                }
            };
        });

        $this->app->bind(BpjsCalculationEngineInterface::class, fn () => new class implements BpjsCalculationEngineInterface
        {
            public function calculateForEmployee($employee, $referenceDate, array $resolvedSalaryLines): array
            {
                return [];
            }
        });

        $this->app->bind(TaxCalculationEngineInterface::class, fn () => new class implements TaxCalculationEngineInterface
        {
            public function calculateMonthly($employee, $referenceDate, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?MonthlyTaxResult
            {
                return new MonthlyTaxResult(
                    taxMethodApplied: TaxMethod::Gross, terCategory: TerCategory::A,
                    taxableGrossIncome: '10000000.00', terRatePercentageUsed: '5.00',
                    pph21Amount: '500000.00', takeHomePayDeduction: '500000.00',
                    grossUpAllowance: '0.00', noTaxIdSurchargeApplied: false, rateSourceId: null,
                );
            }

            public function calculateAnnualReconciliation($employee, $referenceDate, array $priorMonthsInYear, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?AnnualReconciliationResult
            {
                return new AnnualReconciliationResult(
                    taxMethodApplied: TaxMethod::Gross, taxYear: 2026,
                    totalGrossAnnual: '120000000.00', positionCostDeduction: '6000000.00',
                    pensionDeduction: '1200000.00', ptkpAmount: '54000000.00',
                    netAnnualIncome: '112800000.00', pkp: '112800000.00',
                    annualTaxPasal17: '11500000.00', totalWithheldPriorMonths: '5500000.00',
                    finalPeriodAdjustment: '6000000.00', grossUpAllowance: '0.00',
                    noTaxIdSurchargeApplied: false,
                );
            }
        });
    }

    private function makeDecemberRunFor(Employee $employee): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'type' => 'regular',
            'period_year' => 2026, 'period_month' => 12, 'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        return $run->fresh();
    }

    public function test_recap_returns_persisted_reconciliation_for_tax_year(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeDecemberRunFor($employee);

        $response = $this->actingAs($this->admin)->getJson('/api/payroll-reports/annual-tax-recap?'.http_build_query([
            'tax_year' => 2026,
            'company_id' => $this->company->id,
        ]));

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertEquals($employee->employee_number, $rows[0]['employee_number']);
        $this->assertEquals('120000000.00', $rows[0]['total_gross_annual']);
        $this->assertEquals('11500000.00', $rows[0]['annual_tax_pasal17']);
        $this->assertEquals('6000000.00', $rows[0]['final_period_adjustment']);
    }

    public function test_recap_requires_view_payroll_runs_permission(): void
    {
        $userNoPermission = User::factory()->create();

        $this->actingAs($userNoPermission)
            ->getJson('/api/payroll-reports/annual-tax-recap?tax_year=2026')
            ->assertForbidden();
    }

    public function test_bpa1_downloads_successfully_when_reconciliation_exists(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeDecemberRunFor($employee);

        $response = $this->actingAs($this->admin)
            ->get("/api/employees/{$employee->id}/bpa1?tax_year=2026");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_bpa1_returns_404_when_no_reconciliation_for_that_year(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        // Tidak ada payroll run sama sekali buat employee ini.

        $response = $this->actingAs($this->admin)
            ->getJson("/api/employees/{$employee->id}/bpa1?tax_year=2026");

        $response->assertStatus(404);
    }

    public function test_annual_recap_excel_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeDecemberRunFor($employee);

        $this->actingAs($this->admin)
            ->get('/api/payroll-reports/annual-tax-recap/export/excel?tax_year=2026')
            ->assertOk();
    }

    // Recalculate (revisi baru) -> BPA1 harus pakai reconciliation dari
    // revisi CURRENT, bukan revisi lama yang sudah usang.
    public function test_bpa1_uses_current_revision_after_recalculate(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->makeDecemberRunFor($employee);

        // Recalculate.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $this->assertDatabaseCount('employee_annual_tax_reconciliations', 2);

        $response = $this->actingAs($this->admin)->getJson('/api/payroll-reports/annual-tax-recap?tax_year=2026');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'), 'Report cuma boleh nampilin 1 baris (revisi current), bukan 2 dari histori.');
    }
}
