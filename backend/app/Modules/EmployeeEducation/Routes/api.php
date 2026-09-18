<?php

use App\Modules\EmployeeEducation\Controllers\EmployeeEducationController;
use Illuminate\Support\Facades\Route;

// Self-service -- full CRUD, tanpa permission (data biografis milik sendiri).
Route::get('/my-educations', [EmployeeEducationController::class, 'indexMine']);
Route::post('/my-educations', [EmployeeEducationController::class, 'storeMine']);
Route::post('/my-educations/{education}', [EmployeeEducationController::class, 'updateMine']); // form-data + file, pakai POST+_method=PUT dari FE
Route::delete('/my-educations/{education}', [EmployeeEducationController::class, 'destroyMine']);

// Admin -- permission per-resource.
Route::middleware('permission:view employee education')
    ->get('/employees/{employee}/educations', [EmployeeEducationController::class, 'indexForEmployee']);

Route::middleware('permission:create employee education')
    ->post('/employees/{employee}/educations', [EmployeeEducationController::class, 'storeForEmployee']);

Route::middleware('permission:edit employee education')
    ->post('/employees/{employee}/educations/{education}', [EmployeeEducationController::class, 'updateForEmployee']);

Route::middleware('permission:delete employee education')
    ->delete('/employees/{employee}/educations/{education}', [EmployeeEducationController::class, 'destroyForEmployee']);