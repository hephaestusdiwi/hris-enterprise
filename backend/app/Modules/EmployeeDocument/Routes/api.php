<?php

use App\Modules\EmployeeDocument\Controllers\EmployeeDocumentController;
use Illuminate\Support\Facades\Route;

// Self-service -- tanpa permission, sama seperti /my-profile & /my-attendances.
Route::get('/my-documents', [EmployeeDocumentController::class, 'indexMine']);
Route::post('/my-documents', [EmployeeDocumentController::class, 'storeMine']);
Route::delete('/my-documents/{document}', [EmployeeDocumentController::class, 'destroyMine']);

// Admin -- permission per-resource baru, konsisten pola EmployeeAllowance dkk.
Route::middleware('permission:view employee documents')
    ->get('/employees/{employee}/documents', [EmployeeDocumentController::class, 'indexForEmployee']);

Route::middleware('permission:create employee documents')
    ->post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'storeForEmployee']);

Route::middleware('permission:delete employee documents')
    ->delete('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroyForEmployee']);