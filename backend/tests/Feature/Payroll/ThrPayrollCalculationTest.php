<?php

namespace Tests\Feature\Payroll;

use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\ThrPolicy;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use App\Modules\Pph21\DataTransferObjects\MonthlyTaxResult;
use App\Modules\Pph21\Enums\TaxMethod;
use App\Modules\Pph21\Enums\TerCategory;
use App\Modules\SalaryComponent\Enums\SalaryComponentCategory;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert as PHPUnit;
use Tests\TestCase;

/**
 * Test REAL PayrollCalculationEngine (bukan stub interface top-level, TIDAK
 * seperti test lifecycle lain) khusus buat cabang calculateThrForEmployee() —
 * ini logic BARU Fase 7 yang perlu dibuktikan benar. Sub-engine
 * (EmployeeSalaryResolver/Bpjs/Tax) di-fake di level SATU LANGKAH LEBIH
 * DALAM supaya tidak perlu setup Salary Structure/BPJS Setting/Tax Profile
 * yang lengkap (itu tanggung jawab test module masing-masing) — yang
 * dibuktikan di sini murni: THR = basic_salary x prorationFactor, dan
 * amount itu yang di-pass ke Tax/Bpjs engine yang SAMA (bukan implementasi
 * kedua).
 */
class ThrPayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeSalaryResolver(string $basicSalaryAmount): void
    {
        $this->app->bind(EmployeeSalaryResolverInterface::class, function () use ($basicSalaryAmount) {
            return new class($basicSalaryAmount) implements EmployeeSalaryResolverInterface
            {
                public function __construct(private string $amount)
                {
                }

                public function resolveComponents($employee, $referenceDate): array
                {
                    $component = new SalaryComponent([
                        'name' => 'Gaji Pokok',
                        'category' => SalaryComponentCategory::BasicSalary->value,
                        'is_addition' => true,
                        'is_taxable' => true,
                        'include_in_bpjs_base' => true,
                    ]);

                    return [new ResolvedSalaryLine($component, $this->amount, null, null, 'structure')];
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
    }

    private function bindFakeBpjsEngine(): void
    {
        $this->app->bind(BpjsCalculationEngineInterface::class, function () {
            return new class implements BpjsCalculationEngineInterface
            {
                public function calculateForEmployee($employee, $referenceDate, array $resolvedSalaryLines): array
                {
                    return [];
                }
            };
        });
    }

    private function bindFakeTaxEngine(string $expectedGrossEarning, string $pph21Amount): void
    {
        $this->app->bind(TaxCalculationEngineInterface::class, function () use ($expectedGrossEarning, $pph21Amount) {
            return new class($expectedGrossEarning, $pph21Amount) implements TaxCalculationEngineInterface
            {
                public array $capturedGrossAmounts = [];

                public function __construct(private string $expectedGrossEarning, private string $pph21Amount)
                {
                }

                public function calculateMonthly($employee, $referenceDate, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?MonthlyTaxResult
                {
                    // Buktikan engine THR benar-benar ngirim amount yang SAMA
                    // dengan yang sudah diprorata (bukan basic_salary mentah).
                    PHPUnit::assertEquals($this->expectedGrossEarning, $resolvedSalaryLines[0]->amount);

                    return new MonthlyTaxResult(
                        taxMethodApplied: TaxMethod::Gross,
                        terCategory: TerCategory::A,
                        taxableGrossIncome: $this->expectedGrossEarning,
                        terRatePercentageUsed: '5.00',
                        pph21Amount: $this->pph21Amount,
                        takeHomePayDeduction: $this->pph21Amount,
                        grossUpAllowance: '0.00',
                        noTaxIdSurchargeApplied: false,
                        rateSourceId: null,
                    );
                }

                public function calculateAnnualReconciliation($employee, $referenceDate, array $priorMonthsInYear, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?\App\Modules\Pph21\DataTransferObjects\AnnualReconciliationResult
                {
                    return null;
                }
            };
        });
    }

    // 4. THR prorata bekerja — dibuktikan sampai ke gross_earning payslip draft.
    public function test_thr_gross_earning_equals_basic_salary_times_proration_factor(): void
    {
        $this->bindFakeSalaryResolver('6000000.00');
        $this->bindFakeBpjsEngine();
        $this->bindFakeTaxEngine('3000000.00', '150000.00');

        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'join_date' => Carbon::parse('2025-10-01'), // 6 bulan per referenceDate akhir April
        ]);

        ThrPolicy::create([
            'company_id' => $company->id,
            'name' => 'Default',
            'minimum_service_months' => 1,
            'full_service_months' => 12,
            'is_active' => true,
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id,
            'type' => PayrollRunType::Thr->value,
            'period_year' => 2026,
            'period_month' => 4,
            'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);

        $engine = app(PayrollCalculationEngineInterface::class);
        $drafts = $engine->calculateDraftsForRun($run->fresh());
        $draft = $drafts[$employee->id];

        // service months = 6 (Okt 2025 -> Apr 2026), full=12 -> factor 0.5
        $this->assertEquals('3000000.00', $draft->grossEarning);
        $this->assertEquals('150000.00', $draft->taxAmount);
        $this->assertEquals('2850000.00', $draft->netPay);
        $this->assertEquals('0.00', $draft->bpjsEmployeeTotal);
    }

    // 2. THR payroll dapat dibuat & dihitung penuh untuk masa kerja >= full_service_months.
    public function test_thr_gross_earning_is_full_basic_salary_for_full_tenure(): void
    {
        $this->bindFakeSalaryResolver('5000000.00');
        $this->bindFakeBpjsEngine();
        $this->bindFakeTaxEngine('5000000.00', '100000.00');

        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'join_date' => Carbon::parse('2020-01-01'),
        ]);

        ThrPolicy::create([
            'company_id' => $company->id, 'name' => 'Default', 'minimum_service_months' => 1,
            'full_service_months' => 12, 'is_active' => true,
        ]);

        $run = PayrollRun::create([
            'company_id' => $company->id, 'type' => PayrollRunType::Thr->value,
            'period_year' => 2026, 'period_month' => 4, 'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);

        $drafts = app(PayrollCalculationEngineInterface::class)->calculateDraftsForRun($run->fresh());
        $draft = $drafts[$employee->id];

        $this->assertEquals('5000000.00', $draft->grossEarning);
        $this->assertEquals('4900000.00', $draft->netPay);
    }
}
