<?php

namespace Tests\Feature\Payroll;

use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Enums\NonRegularComponentCategory;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use App\Modules\Pph21\DataTransferObjects\MonthlyTaxResult;
use App\Modules\Pph21\Enums\TaxMethod;
use App\Modules\Pph21\Enums\TerCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test REAL PayrollCalculationEngine khusus cabang
 * calculateNonRegularForEmployee(). Bpjs engine di-fake netral (kontribusi 0,
 * konsisten default include_in_bpjs_base=false), Tax engine di-fake supaya
 * kelihatan jelas gross yang di-pass adalah TOTAL earning non-reguler
 * (bukan basic salary — karena non-regular run memang tidak menyentuh
 * salary structure sama sekali).
 */
class NonRegularPayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function bindNeutralBpjsEngine(): void
    {
        $this->app->bind(BpjsCalculationEngineInterface::class, fn () => new class implements BpjsCalculationEngineInterface
        {
            public function calculateForEmployee($employee, $referenceDate, array $resolvedSalaryLines): array
            {
                return [];
            }
        });
    }

    private function bindFakeTaxEngine(string $pph21Amount): void
    {
        $this->app->bind(TaxCalculationEngineInterface::class, fn () => new class($pph21Amount) implements TaxCalculationEngineInterface
        {
            public function __construct(private string $pph21Amount)
            {
            }

            public function calculateMonthly($employee, $referenceDate, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?MonthlyTaxResult
            {
                $gross = array_reduce($resolvedSalaryLines, fn ($carry, $line) => bcadd($carry, $line->amount, 2), '0.00');

                return new MonthlyTaxResult(
                    taxMethodApplied: TaxMethod::Gross,
                    terCategory: TerCategory::A,
                    taxableGrossIncome: $gross,
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
        });
    }

    private function makeRunWithParticipant(Company $company, Employee $employee): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'type' => PayrollRunType::NonRegular->value,
            'period_year' => 2026,
            'period_month' => 5,
            'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);

        return $run->fresh();
    }

    // 5. Non-regular component dapat dibuat (lewat model langsung, CRUD HTTP
    // dites terpisah di NonRegularPayrollComponentTest).
    public function test_component_category_default_direction_is_correct(): void
    {
        $company = Company::factory()->create();
        $bonus = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'BONUS', 'name' => 'Bonus Tahunan',
            'category' => NonRegularComponentCategory::Bonus->value, 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
        $deduction = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'POTONGAN', 'name' => 'Potongan Kasbon',
            'category' => NonRegularComponentCategory::OneTimeDeduction->value, 'is_taxable' => false, 'include_in_bpjs_base' => false,
        ]);

        $this->assertTrue($bonus->resolvedIsAddition());
        $this->assertFalse($deduction->resolvedIsAddition());
    }

    // 6 & 7. Bonus/Incentive/Commission/One-Time Earning diproses & masuk payslip.
    public function test_multiple_earning_inputs_are_summed_into_gross_and_taxed(): void
    {
        $this->bindNeutralBpjsEngine();
        $this->bindFakeTaxEngine('150000.00');

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $bonus = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'BONUS', 'name' => 'Bonus Kinerja',
            'category' => NonRegularComponentCategory::Bonus->value, 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
        $incentive = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'INSENTIF', 'name' => 'Insentif Proyek',
            'category' => NonRegularComponentCategory::Incentive->value, 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);

        EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $bonus->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '2000000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);
        EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $incentive->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '1000000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);

        $run = $this->makeRunWithParticipant($company, $employee);
        $drafts = app(PayrollCalculationEngineInterface::class)->calculateDraftsForRun($run);
        $draft = $drafts[$employee->id];

        $this->assertEquals('3000000.00', $draft->grossEarning);
        $this->assertEquals('150000.00', $draft->taxAmount);
        $this->assertEquals('2850000.00', $draft->netPay);
        $this->assertCount(3, $draft->lines); // 2 earning line + 1 tax line
    }

    // 8. One-Time Deduction masuk payslip sebagai pengurang, bukan penambah.
    public function test_one_time_deduction_reduces_net_pay_and_is_not_taxed(): void
    {
        $this->bindNeutralBpjsEngine();
        $this->bindFakeTaxEngine('50000.00');

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $earning = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'COMM', 'name' => 'Commission',
            'category' => NonRegularComponentCategory::Commission->value, 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);
        $deduction = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'POTONGAN-ALAT', 'name' => 'Ganti Rugi Alat',
            'category' => NonRegularComponentCategory::OneTimeDeduction->value, 'is_taxable' => false, 'include_in_bpjs_base' => false,
        ]);

        EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $earning->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '1000000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);
        EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $deduction->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '200000.00',
            'is_addition' => false, 'status' => EmployeeNonRegularInputStatus::Ready->value,
        ]);

        $run = $this->makeRunWithParticipant($company, $employee);
        $draft = app(PayrollCalculationEngineInterface::class)->calculateDraftsForRun($run)[$employee->id];

        // gross earning cuma dari commission (1jt), deduction TIDAK masuk gross
        $this->assertEquals('1000000.00', $draft->grossEarning);
        $this->assertEquals('200000.00', $draft->manualDeductionTotal);
        // net = 1.000.000 - 200.000 (deduction) - 0 (bpjs) - 50.000 (tax) = 750.000
        $this->assertEquals('750000.00', $draft->netPay);
    }

    // Draft/Void input TIDAK ikut dihitung — cuma status Ready yang diproses.
    public function test_only_ready_status_inputs_are_included_in_calculation(): void
    {
        $this->bindNeutralBpjsEngine();
        $this->bindFakeTaxEngine('0.00');

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $component = NonRegularPayrollComponent::create([
            'company_id' => $company->id, 'code' => 'BONUS2', 'name' => 'Bonus',
            'category' => NonRegularComponentCategory::Bonus->value, 'is_taxable' => true, 'include_in_bpjs_base' => false,
        ]);

        EmployeeNonRegularInput::create([
            'employee_id' => $employee->id, 'non_regular_payroll_component_id' => $component->id,
            'payroll_period_year' => 2026, 'payroll_period_month' => 5, 'amount' => '500000.00',
            'is_addition' => true, 'status' => EmployeeNonRegularInputStatus::Draft->value, // belum Ready
        ]);

        $run = $this->makeRunWithParticipant($company, $employee);
        $draft = app(PayrollCalculationEngineInterface::class)->calculateDraftsForRun($run)[$employee->id];

        $this->assertEquals('0.00', $draft->grossEarning);
    }
}
