<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}