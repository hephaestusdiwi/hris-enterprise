<?php

namespace App\Modules\Pph21\Services;

use App\Modules\Bpjs\Enums\BpjsProgram;
use App\Modules\Employee\Models\Employee;
use App\Modules\Pph21\Contracts\EmployeePtkpStatusResolverInterface;
use App\Modules\Pph21\Contracts\PtkpResolverInterface;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use App\Modules\Pph21\Contracts\TerRateResolverInterface;
use App\Modules\Pph21\DataTransferObjects\AnnualReconciliationResult;
use App\Modules\Pph21\DataTransferObjects\MonthlyTaxResult;
use App\Modules\Pph21\Enums\TaxMethod;
use App\Modules\Pph21\Enums\TerCategory;
use App\Modules\Pph21\Models\CompanyTaxSetting;
use App\Modules\Pph21\Models\EmployeeTaxProfile;
use App\Modules\Pph21\Support\Pph21Math;
use Carbon\Carbon;

class TaxCalculationEngine implements TaxCalculationEngineInterface
{
    private const MAX_CONVERGENCE_ITERATIONS = 5;

    public function __construct(
        private PtkpResolverInterface $ptkpResolver,
        private TerRateResolverInterface $terRateResolver,
        private EmployeePtkpStatusResolverInterface $ptkpStatusResolver,
        private Pasal17Calculator $pasal17Calculator,
    ) {
    }

    public function calculateMonthly(
        Employee $employee,
        Carbon $referenceDate,
        array $resolvedSalaryLines,
        array $resolvedBpjsContributions = [],
    ): ?MonthlyTaxResult {
        $profile = EmployeeTaxProfile::where('employee_id', $employee->id)->first();

        if (! $profile) {
            return null;
        }

        $ptkpStatusRow = $this->ptkpStatusResolver->resolveForTaxYear($employee->id, $referenceDate->year);

        if (! $ptkpStatusRow) {
            return null;
        }

        $category = $ptkpStatusRow->ptkp_status->terCategory();
        $companySetting = CompanyTaxSetting::where('company_id', $employee->company_id)->first();
        $taxMethod = $profile->tax_method ?? $companySetting?->default_tax_method ?? TaxMethod::Gross;

        $taxableGross = $this->sumTaxableWage($resolvedSalaryLines);
        $surchargeMultiplier = $this->surchargeMultiplier($profile, $companySetting);

        return match ($taxMethod) {
            TaxMethod::Gross => $this->calculateGrossMonthly($taxableGross, $category, $referenceDate, $surchargeMultiplier, $profile),
            TaxMethod::Netto => $this->calculateNettoMonthly($taxableGross, $category, $referenceDate, $surchargeMultiplier, $profile),
            TaxMethod::GrossUp => $this->calculateGrossUpMonthly($taxableGross, $category, $referenceDate, $surchargeMultiplier, $profile),
        };
    }

    public function calculateAnnualReconciliation(
        Employee $employee,
        Carbon $referenceDate,
        array $priorMonthsInYear,
        array $resolvedSalaryLines,
        array $resolvedBpjsContributions = [],
    ): ?AnnualReconciliationResult {
        $profile = EmployeeTaxProfile::where('employee_id', $employee->id)->first();

        if (! $profile) {
            return null;
        }

        $ptkpStatusRow = $this->ptkpStatusResolver->resolveForTaxYear($employee->id, $referenceDate->year);

        if (! $ptkpStatusRow) {
            return null;
        }

        $companySetting = CompanyTaxSetting::where('company_id', $employee->company_id)->first();
        $taxMethod = $profile->tax_method ?? $companySetting?->default_tax_method ?? TaxMethod::Gross;
        $surchargeMultiplier = $this->surchargeMultiplier($profile, $companySetting);

        // 1. Total bruto setahun = seluruh bulan sebelumnya (disuplai caller) + bulan berjalan.
        $currentMonthGross = $this->sumTaxableWage($resolvedSalaryLines);
        $totalGrossAnnual = $currentMonthGross;
        $totalWithheldPriorMonths = '0.00';
        $totalPensionPriorMonths = '0.00';

        foreach ($priorMonthsInYear as $record) {
            $totalGrossAnnual = Pph21Math::add($totalGrossAnnual, $record->grossIncome);
            $totalWithheldPriorMonths = Pph21Math::add($totalWithheldPriorMonths, $record->pph21Withheld);
            $totalPensionPriorMonths = Pph21Math::add($totalPensionPriorMonths, $record->pensionContribution);
        }

        // 2. Biaya jabatan — 5% dari bruto tahunan, dibatasi cap bulanan x jumlah bulan & cap tahunan [Regulasi Pemerintah].
        $positionCostPercentage = $companySetting?->position_cost_percentage ?? '5.00';
        $positionCostAnnualCap = $companySetting?->position_cost_annual_cap ?? '6000000.00';
        $rawPositionCost = Pph21Math::mul($totalGrossAnnual, Pph21Math::div((string) $positionCostPercentage, '100', 6));
        $positionCostDeduction = Pph21Math::min($rawPositionCost, (string) $positionCostAnnualCap);

        // 3. Iuran pensiun (JHT porsi karyawan) — bulan berjalan + histori.
        $currentMonthPension = isset($resolvedBpjsContributions[BpjsProgram::Jht->value])
            ? $resolvedBpjsContributions[BpjsProgram::Jht->value]->employeeAmount
            : '0.00';
        $pensionDeduction = Pph21Math::add($totalPensionPriorMonths, $currentMonthPension);

        // 4. Netto tahunan & PTKP.
        $netAnnualIncome = Pph21Math::sub(Pph21Math::sub($totalGrossAnnual, $positionCostDeduction), $pensionDeduction);
        $ptkpConfig = $this->ptkpResolver->resolveActiveVersion($ptkpStatusRow->ptkp_status, $referenceDate);
        $ptkpAmount = $ptkpConfig?->annual_amount ?? '0.00';

        // 5. PKP, dibulatkan ke bawah per seribu rupiah [Regulasi Pemerintah].
        $pkpRaw = Pph21Math::max('0.00', Pph21Math::sub($netAnnualIncome, (string) $ptkpAmount));
        $pkp = Pph21Math::floorToThousand($pkpRaw);

        // 6. Pajak terutang setahun (Pasal 17 progresif berlapis).
        $annualTaxBase = $this->pasal17Calculator->calculate($pkp, $referenceDate);
        $annualTax = Pph21Math::mul($annualTaxBase, $surchargeMultiplier);

        $grossUpAllowance = '0.00';

        if ($taxMethod === TaxMethod::GrossUp) {
            // Catatan: ini penyesuaian di masa pajak terakhir, BUKAN gross-up penuh
            // yang mengubah ulang penghasilan bruto sepanjang tahun (bulan-bulan
            // sebelumnya sudah final via calculateMonthly masing-masing periode).
            // [Inferensi/pendekatan teknis] — lihat catatan desain.
            $grossUpAllowance = $annualTax;
        }

        $finalPeriodAdjustment = Pph21Math::sub($annualTax, $totalWithheldPriorMonths);

        return new AnnualReconciliationResult(
            taxMethodApplied: $taxMethod,
            taxYear: $referenceDate->year,
            totalGrossAnnual: $totalGrossAnnual,
            positionCostDeduction: $positionCostDeduction,
            pensionDeduction: $pensionDeduction,
            ptkpAmount: (string) $ptkpAmount,
            netAnnualIncome: $netAnnualIncome,
            pkp: $pkp,
            annualTaxPasal17: $annualTax,
            totalWithheldPriorMonths: $totalWithheldPriorMonths,
            finalPeriodAdjustment: $finalPeriodAdjustment,
            grossUpAllowance: $grossUpAllowance,
            noTaxIdSurchargeApplied: ! $profile->has_tax_id,
        );
    }

    /**
     * @param  array<int, \App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine>  $resolvedSalaryLines
     */
    private function sumTaxableWage(array $resolvedSalaryLines): string
    {
        $total = '0.00';

        foreach ($resolvedSalaryLines as $line) {
            if (! $line->component->is_taxable) {
                continue;
            }

            $total = Pph21Math::add($total, $line->amount ?? '0.00');
        }

        return $total;
    }

    private function surchargeMultiplier(EmployeeTaxProfile $profile, ?CompanyTaxSetting $companySetting): string
    {
        if ($profile->has_tax_id) {
            return '1.00';
        }

        $surchargePercentage = $companySetting?->no_npwp_surcharge_percentage ?? '20.00';

        return Pph21Math::add('1.00', Pph21Math::div((string) $surchargePercentage, '100', 6));
    }

    private function calculateGrossMonthly(
        string $taxableGross,
        TerCategory $category,
        Carbon $referenceDate,
        string $surchargeMultiplier,
        EmployeeTaxProfile $profile,
    ): ?MonthlyTaxResult {
        $bracket = $this->terRateResolver->resolveBracket($category, $taxableGross, $referenceDate);

        if (! $bracket) {
            return null;
        }

        $baseTax = Pph21Math::mul($taxableGross, Pph21Math::div((string) $bracket->rate_percentage, '100', 6));
        $finalTax = Pph21Math::mul($baseTax, $surchargeMultiplier);

        return new MonthlyTaxResult(
            taxMethodApplied: TaxMethod::Gross,
            terCategory: $category,
            taxableGrossIncome: $taxableGross,
            terRatePercentageUsed: (string) $bracket->rate_percentage,
            pph21Amount: $finalTax,
            takeHomePayDeduction: $finalTax,
            grossUpAllowance: '0.00',
            noTaxIdSurchargeApplied: ! $profile->has_tax_id,
            rateSourceId: $bracket->id,
        );
    }

    private function calculateNettoMonthly(
        string $taxableGross,
        TerCategory $category,
        Carbon $referenceDate,
        string $surchargeMultiplier,
        EmployeeTaxProfile $profile,
    ): ?MonthlyTaxResult {
        $bracket = $this->terRateResolver->resolveBracket($category, $taxableGross, $referenceDate);

        if (! $bracket) {
            return null;
        }

        $baseTax = Pph21Math::mul($taxableGross, Pph21Math::div((string) $bracket->rate_percentage, '100', 6));
        $finalTax = Pph21Math::mul($baseTax, $surchargeMultiplier);

        // Netto: company yang nanggung, THP karyawan tidak berkurang sama sekali.
        return new MonthlyTaxResult(
            taxMethodApplied: TaxMethod::Netto,
            terCategory: $category,
            taxableGrossIncome: $taxableGross,
            terRatePercentageUsed: (string) $bracket->rate_percentage,
            pph21Amount: $finalTax,
            takeHomePayDeduction: '0.00',
            grossUpAllowance: '0.00',
            noTaxIdSurchargeApplied: ! $profile->has_tax_id,
            rateSourceId: $bracket->id,
        );
    }

    private function calculateGrossUpMonthly(
        string $taxableGross,
        TerCategory $category,
        Carbon $referenceDate,
        string $surchargeMultiplier,
        EmployeeTaxProfile $profile,
    ): ?MonthlyTaxResult {
        // Solve allowance = (taxableGross + allowance) * rate * surchargeMultiplier
        // secara iteratif — konvergen cepat karena rate TER selalu < 1, dan otomatis
        // nangkep kasus bracket berubah begitu allowance nambahin gross ke lapisan atas.
        $allowance = '0.00';
        $bracket = null;

        for ($i = 0; $i < self::MAX_CONVERGENCE_ITERATIONS; $i++) {
            $grossedUp = Pph21Math::add($taxableGross, $allowance);
            $bracket = $this->terRateResolver->resolveBracket($category, $grossedUp, $referenceDate);

            if (! $bracket) {
                return null;
            }

            $baseTax = Pph21Math::mul($grossedUp, Pph21Math::div((string) $bracket->rate_percentage, '100', 6));
            $newAllowance = Pph21Math::mul($baseTax, $surchargeMultiplier);

            if ($newAllowance === $allowance) {
                break;
            }

            $allowance = $newAllowance;
        }

        return new MonthlyTaxResult(
            taxMethodApplied: TaxMethod::GrossUp,
            terCategory: $category,
            taxableGrossIncome: $taxableGross,
            terRatePercentageUsed: (string) $bracket->rate_percentage,
            pph21Amount: $allowance,
            takeHomePayDeduction: '0.00', // net-neutral: tunjangan pajak persis nutup potongannya
            grossUpAllowance: $allowance,
            noTaxIdSurchargeApplied: ! $profile->has_tax_id,
            rateSourceId: $bracket->id,
        );
    }
}