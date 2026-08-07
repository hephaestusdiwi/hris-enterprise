<?php

namespace App\Modules\Bpjs\Services;

use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Bpjs\Contracts\BpjsCompanyRegistrationResolverInterface;
use App\Modules\Bpjs\Contracts\BpjsJkkRiskClassResolverInterface;
use App\Modules\Bpjs\Contracts\BpjsRateResolverInterface;
use App\Modules\Bpjs\DataTransferObjects\ResolvedBpjsContribution;
use App\Modules\Bpjs\Enums\BpjsCostBearer;
use App\Modules\Bpjs\Enums\BpjsProgram;
use App\Modules\Bpjs\Models\BpjsRateConfig;
use App\Modules\Bpjs\Models\CompanyBpjsSetting;
use App\Modules\Bpjs\Models\EmployeeBpjsParticipation;
use App\Modules\Bpjs\Support\BpjsMath;
use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;

class BpjsCalculationEngine implements BpjsCalculationEngineInterface
{
    public function __construct(
        private BpjsRateResolverInterface $rateResolver,
        private BpjsJkkRiskClassResolverInterface $riskClassResolver,
        private BpjsCompanyRegistrationResolverInterface $registrationResolver,
    ) {
    }

    public function calculateForEmployee(Employee $employee, Carbon $referenceDate, array $resolvedSalaryLines): array
    {
        $participation = EmployeeBpjsParticipation::where('employee_id', $employee->id)->first();

        if (! $participation) {
            return [];
        }

        $companySetting = CompanyBpjsSetting::where('company_id', $employee->company_id)->first();
        $wageBase = $this->sumBpjsBaseWage($resolvedSalaryLines);

        $results = [];

        if ($participation->bpjs_health_number) {
            $bearer = $this->resolveCostBearer(
                $participation->bpjs_health_cost_bearer,
                $companySetting?->default_health_cost_bearer,
            );

            $result = $this->calculateFlatProgram($employee->company_id, BpjsProgram::Kesehatan, $wageBase, $referenceDate, $bearer);

            if ($result) {
                $results[BpjsProgram::Kesehatan->value] = $result;
            }
        }

        if ($participation->bpjs_employment_number) {
            $jhtBearer = $this->resolveCostBearer(
                $participation->jht_cost_bearer,
                $companySetting?->default_jht_cost_bearer,
            );

            if ($jhtBearer !== BpjsCostBearer::NotParticipating) {
                $result = $this->calculateFlatProgram($employee->company_id, BpjsProgram::Jht, $wageBase, $referenceDate, $jhtBearer);

                if ($result) {
                    $results[BpjsProgram::Jht->value] = $result;
                }
            }

            $jkmResult = $this->calculateEmployerOnlyFlatProgram($employee->company_id, BpjsProgram::Jkm, $wageBase, $referenceDate);

            if ($jkmResult) {
                $results[BpjsProgram::Jkm->value] = $jkmResult;
            }

            if ($participation->bpjs_registration_npp_number) {
                $jkkResult = $this->calculateJkk(
                    $employee->company_id,
                    $participation->bpjs_registration_npp_number,
                    $wageBase,
                    $referenceDate,
                );

                if ($jkkResult) {
                    $results[BpjsProgram::Jkk->value] = $jkkResult;
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<int, \App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine>  $resolvedSalaryLines
     */
    private function sumBpjsBaseWage(array $resolvedSalaryLines): string
    {
        $total = '0.00';

        foreach ($resolvedSalaryLines as $line) {
            if (! $line->component->include_in_bpjs_base) {
                continue;
            }

            $total = BpjsMath::add($total, $line->amount ?? '0.00');
        }

        return $total;
    }

    private function resolveCostBearer(string $employeeOverride, ?string $companyDefault): BpjsCostBearer
    {
        $bearer = BpjsCostBearer::from($employeeOverride);

        if ($bearer !== BpjsCostBearer::DefaultPolicy) {
            return $bearer;
        }

        return $companyDefault ? BpjsCostBearer::from($companyDefault) : BpjsCostBearer::EmployeeBorne;
    }

    private function applyWageCap(string $wageBase, ?BpjsRateConfig $rate): string
    {
        if (! $rate || $rate->wage_base_cap === null) {
            return $wageBase;
        }

        return BpjsMath::min($wageBase, (string) $rate->wage_base_cap);
    }

    /**
     * Kesehatan & JHT — punya porsi karyawan, dipengaruhi cost bearer.
     */
    private function calculateFlatProgram(
        int $companyId,
        BpjsProgram $program,
        string $wageBase,
        Carbon $referenceDate,
        BpjsCostBearer $bearer,
    ): ?ResolvedBpjsContribution {
        $rate = $this->rateResolver->resolveActiveVersion($companyId, $program, $referenceDate);

        if (! $rate) {
            return null;
        }

        $wageBaseUsed = $this->applyWageCap($wageBase, $rate);

        $employeeAmount = BpjsMath::mul($wageBaseUsed, BpjsMath::div((string) ($rate->employee_rate_percentage ?? '0'), '100', 6));
        $employerAmount = BpjsMath::mul($wageBaseUsed, BpjsMath::div((string) ($rate->employer_rate_percentage ?? '0'), '100', 6));

        if ($bearer === BpjsCostBearer::CompanyBorne) {
            $employerAmount = BpjsMath::add($employerAmount, $employeeAmount);
            $employeeAmount = '0.00';
        }

        return new ResolvedBpjsContribution($program, $wageBaseUsed, $employeeAmount, $employerAmount, $bearer, $rate->id);
    }

    /**
     * JKM — 100% company, tidak ada cost bearer override.
     */
    private function calculateEmployerOnlyFlatProgram(
        int $companyId,
        BpjsProgram $program,
        string $wageBase,
        Carbon $referenceDate,
    ): ?ResolvedBpjsContribution {
        $rate = $this->rateResolver->resolveActiveVersion($companyId, $program, $referenceDate);

        if (! $rate) {
            return null;
        }

        $wageBaseUsed = $this->applyWageCap($wageBase, $rate);
        $employerAmount = BpjsMath::mul($wageBaseUsed, BpjsMath::div((string) ($rate->employer_rate_percentage ?? '0'), '100', 6));

        return new ResolvedBpjsContribution($program, $wageBaseUsed, '0.00', $employerAmount, BpjsCostBearer::CompanyBorne, $rate->id);
    }

    /**
     * JKK — tarif tidak datang dari BpjsRateConfig, tapi dari kelas risiko NPP
     * yang employee terdaftar. wage_base_cap (kalau company set) tetap diambil
     * dari BpjsRateConfig program=jkk supaya konsisten dengan program lain.
     */
    private function calculateJkk(int $companyId, string $nppNumber, string $wageBase, Carbon $referenceDate): ?ResolvedBpjsContribution
    {
        $registration = $this->registrationResolver->resolveActiveVersion($companyId, $nppNumber, $referenceDate);

        if (! $registration) {
            return null;
        }

        $riskRate = $this->riskClassResolver->resolveActiveVersion($registration->risk_class, $referenceDate);

        if (! $riskRate) {
            return null;
        }

        $rateConfig = $this->rateResolver->resolveActiveVersion($companyId, BpjsProgram::Jkk, $referenceDate);
        $wageBaseUsed = $this->applyWageCap($wageBase, $rateConfig);

        $employerAmount = BpjsMath::mul($wageBaseUsed, BpjsMath::div((string) $riskRate->employer_rate_percentage, '100', 6));

        return new ResolvedBpjsContribution(BpjsProgram::Jkk, $wageBaseUsed, '0.00', $employerAmount, BpjsCostBearer::CompanyBorne, $riskRate->id);
    }
}