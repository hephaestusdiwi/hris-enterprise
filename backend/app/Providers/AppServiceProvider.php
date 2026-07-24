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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}