<?php

use App\Modules\JobVacancy\Controllers\JobVacancyController;
use App\Modules\JobVacancy\Controllers\JobVacancyPublicController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('job-vacancies')->group(function () {
    Route::get('/', [JobVacancyController::class, 'index']);
    Route::post('/', [JobVacancyController::class, 'store']);
    Route::get('/{jobVacancy}', [JobVacancyController::class, 'show']);
    Route::post('/{jobVacancy}/publish', [JobVacancyController::class, 'publish']);
    Route::post('/{jobVacancy}/pause', [JobVacancyController::class, 'pause']);
    Route::post('/{jobVacancy}/close', [JobVacancyController::class, 'close']);
    Route::post('/{jobVacancy}/cancel', [JobVacancyController::class, 'cancel']);
    Route::post('/{jobVacancy}/archive', [JobVacancyController::class, 'archive']);
});

// Career site — publik, tanpa auth:sanctum, throttle terpisah
Route::middleware('throttle:60,1')->prefix('careers')->group(function () {
    Route::get('/vacancies', [JobVacancyPublicController::class, 'index']);
    Route::get('/vacancies/{slug}', [JobVacancyPublicController::class, 'show']);
});