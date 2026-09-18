<?php

use App\Modules\EmployeeExperience\Controllers\EmployeeExperienceController;
use Illuminate\Support\Facades\Route;

// Self-service -- full CRUD, tanpa permission.
Route::get('/my-experiences', [EmployeeExperienceController::class, 'indexMine']);
Route::post('/my-experiences', [EmployeeExperienceController::class, 'storeMine']);
Route::put('/my-experiences/{experience}', [EmployeeExperienceController::class, 'updateMine']);
Route::delete('/my-experiences/{experience}', [EmployeeExperienceController::class, 'destroyMine']);

// Admin -- permission per-resource.
Route::middleware('permission:view employee experience')
    ->get('/employees/{employee}/experiences', [EmployeeExperienceController::class, 'indexForEmployee']);

Route::middleware('permission:create employee experience')
    ->post('/employees/{employee}/experiences', [EmployeeExperienceController::class, 'storeForEmployee']);

Route::middleware('permission:edit employee experience')
    ->put('/employees/{employee}/experiences/{experience}', [EmployeeExperienceController::class, 'updateForEmployee']);

Route::middleware('permission:delete employee experience')
    ->delete('/employees/{employee}/experiences/{experience}', [EmployeeExperienceController::class, 'destroyForEmployee']);