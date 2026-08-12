<?php

namespace App\Providers;

use App\Modules\Attendance\Contracts\AttendanceCalculationEngineInterface;
use App\Modules\Attendance\Contracts\HolidayCheckerInterface;
use App\Modules\Attendance\Contracts\LeaveCheckerInterface;
use App\Modules\Attendance\Services\AttendanceCalculationEngine;
use App\Modules\Attendance\Services\HolidayChecker;
use App\Modules\Attendance\Services\NullLeaveChecker;
use App\Modules\FaceRecognition\Contracts\FaceRecognitionServiceInterface;
use App\Modules\FaceRecognition\Services\HttpFaceRecognitionService;
use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Bpjs\Contracts\BpjsCompanyRegistrationResolverInterface;
use App\Modules\Bpjs\Contracts\BpjsJkkRiskClassResolverInterface;
use App\Modules\Bpjs\Contracts\BpjsRateResolverInterface;

use App\Modules\Bpjs\Services\BpjsCalculationEngine;
use App\Modules\Bpjs\Services\BpjsCompanyRegistrationResolver;
use App\Modules\Bpjs\Services\BpjsJkkRiskClassResolver;
use App\Modules\Bpjs\Services\BpjsRateResolver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

use App\Modules\Pph21\Contracts\EmployeePtkpStatusResolverInterface;
use App\Modules\Pph21\Contracts\PtkpResolverInterface;
use App\Modules\Pph21\Contracts\TaxBracketResolverInterface;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use App\Modules\Pph21\Contracts\TerRateResolverInterface;
use App\Modules\Pph21\Services\EmployeePtkpStatusResolver;
use App\Modules\Pph21\Services\PtkpResolver;
use App\Modules\Pph21\Services\TaxBracketResolver;
use App\Modules\Pph21\Services\TaxCalculationEngine;
use App\Modules\Pph21\Services\TerRateResolver;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\Services\PayrollCalculationEngine;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FaceRecognitionServiceInterface::class, HttpFaceRecognitionService::class);

        $this->app->bind(HolidayCheckerInterface::class, HolidayChecker::class);
        $this->app->bind(LeaveCheckerInterface::class, NullLeaveChecker::class);
        $this->app->bind(AttendanceCalculationEngineInterface::class, AttendanceCalculationEngine::class);
        $this->app->bind(
            \App\Modules\WorkingSchedule\Contracts\WorkingScheduleResolverInterface::class,
            \App\Modules\WorkingSchedule\Services\WorkingScheduleResolver::class,
        );
        $this->app->bind(
            \App\Modules\LeaveBalance\Contracts\LeaveQuotaProrationStrategyInterface::class,
            \App\Modules\LeaveBalance\Strategies\MonthlyProratedQuotaStrategy::class,
        );
        $this->app->bind(
            \App\Modules\SalaryStructure\Contracts\SalaryStructureResolverInterface::class,
            \App\Modules\SalaryStructure\Services\SalaryStructureResolver::class,
        );
        $this->app->bind(
            \App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface::class,
            \App\Modules\EmployeeSalary\Services\EmployeeSalaryResolver::class,
        );
        $this->app->bind(
            \App\Modules\Employee\Contracts\EmployeeScopeInterface::class,
            \App\Modules\Employee\Services\EmployeeScope::class,
        );

        $this->app->bind(
            \App\Modules\EmployeeSalary\Contracts\EmployeeSalaryScopeInterface::class,
            \App\Modules\EmployeeSalary\Services\EmployeeSalaryScope::class,
        );

        $this->app->bind(
            \App\Modules\HiringRequisition\Contracts\HiringRequisitionScopeInterface::class,
            \App\Modules\HiringRequisition\Services\HiringRequisitionScope::class,
        );
        $this->app->bind(
            \App\Modules\EmployeeMovement\Contracts\EmployeeMovementScopeInterface::class,
            \App\Modules\EmployeeMovement\Services\EmployeeMovementScope::class,
        );

        $this->app->bind(BpjsRateResolverInterface::class, BpjsRateResolver::class);
        $this->app->bind(BpjsJkkRiskClassResolverInterface::class, BpjsJkkRiskClassResolver::class);
        $this->app->bind(BpjsCompanyRegistrationResolverInterface::class, BpjsCompanyRegistrationResolver::class);
        $this->app->bind(BpjsCalculationEngineInterface::class, BpjsCalculationEngine::class);
        $this->app->bind(PtkpResolverInterface::class, PtkpResolver::class);
        $this->app->bind(TerRateResolverInterface::class, TerRateResolver::class);
        $this->app->bind(TaxBracketResolverInterface::class, TaxBracketResolver::class);
        $this->app->bind(EmployeePtkpStatusResolverInterface::class, EmployeePtkpStatusResolver::class);
        $this->app->bind(TaxCalculationEngineInterface::class, TaxCalculationEngine::class);
        $this->app->bind(PayrollCalculationEngineInterface::class, PayrollCalculationEngine::class);
        $this->app->bind(
            \App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface::class,
            \App\Modules\Employee\Services\EmployeeHierarchyService::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'company' => \App\Modules\Company\Models\Company::class,
            'branch' => \App\Modules\Branch\Models\Branch::class,
            'department' => \App\Modules\Department\Models\Department::class,
            'position' => \App\Modules\Position\Models\Position::class,
            'employee' => \App\Modules\Employee\Models\Employee::class,
        ]);
        \App\Modules\Employee\Models\Employee::observe(
            $this->app->make(\App\Modules\LeaveBalance\Observers\EmployeeLeaveBalanceObserver::class)
        );
        \App\Modules\Employee\Models\Employee::observe(
            $this->app->make(\App\Modules\Employee\Observers\EmployeeEmploymentStatusObserver::class)
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Modules\Employee\Models\Employee::class,
            \App\Modules\Employee\Policies\EmployeePolicy::class,
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Modules\EmployeeSalary\Models\EmployeeSalary::class,
            \App\Modules\EmployeeSalary\Policies\EmployeeSalaryPolicy::class,
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Modules\HiringRequisition\Models\HiringRequisition::class,
            \App\Modules\HiringRequisition\Policies\HiringRequisitionPolicy::class,
        );
    }
}