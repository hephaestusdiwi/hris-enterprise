<?php // backend/app/Modules/Candidate/Routes/api.php
use App\Modules\Candidate\Controllers\CandidateController;
use App\Modules\Candidate\Controllers\CandidatePublicController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('candidates')->group(function () {
    Route::get('/', [CandidateController::class, 'index']);
    Route::get('/{candidate}', [CandidateController::class, 'show']);
    Route::post('/{candidate}/reconsider', [CandidateController::class, 'reconsider']);
});

// Career site — publik, gabung throttle group yang sama dengan JobVacancy
Route::middleware('throttle:60,1')->prefix('careers')->group(function () {
    Route::post('/vacancies/{slug}/apply', [CandidatePublicController::class, 'store']);
});