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
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

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
    }
}