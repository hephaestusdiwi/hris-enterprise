<?php

use App\Modules\Employee\Controllers\ContractProbationController;
use App\Modules\Employee\Controllers\EmployeeController;
use App\Modules\Employee\Controllers\EmployeeFaceController;
use App\Modules\Employee\Controllers\EmployeePhotoController;
use App\Modules\Employee\Controllers\EmployeeProfileController;
use App\Modules\Employee\Controllers\EmployeeQrController;
use Illuminate\Support\Facades\Route;

// Employee Profile (self-service) -- General tab ala Mekari Talenta.
// Tanpa permission khusus, sama seperti /my-attendances & /my-leave-requests:
// setiap user yang login & punya Employee terhubung boleh akses punya sendiri.
Route::get('/my-profile', [EmployeeProfileController::class, 'show']);
Route::put('/my-profile', [EmployeeProfileController::class, 'update']);

// People Directory & Company Org Chart -- self-service, tanpa permission,
// field terbatas (nama, foto, posisi, department, manager). Endpoint admin
// /employees/org-chart yang lama TIDAK diubah/disentuh sama sekali.
Route::get('/people-directory', [EmployeeController::class, 'directory']);
Route::get('/people-directory/{employee}', [EmployeeController::class, 'directoryShow']);
Route::get('/company-org-chart', [EmployeeController::class, 'companyOrgChart']);

Route::middleware('permission:view employees')->group(function () {
    Route::get('/employees/next-number', [EmployeeController::class, 'nextNumber']);
    Route::get('/employees/org-chart', [EmployeeController::class, 'orgChart']);
    Route::get('/employees/available-users', [EmployeeController::class, 'availableUsers']);
    Route::get('/employees/contract-probation', [ContractProbationController::class, 'index']);
    Route::get('/employees/contract-probation/summary', [ContractProbationController::class, 'summary']);
    // Export/template WAJIB didaftarkan sebelum /employees/{employee} --
    // kalau enggak, "export"/"import-template" bakal ke-tangkep sebagai
    // {employee} oleh route wildcard di bawahnya.
    Route::get('/employees/export', [EmployeeController::class, 'export']);
    Route::get('/employees/import-template', [EmployeeController::class, 'importTemplate']);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
    Route::get('/employees/{employee}/hierarchy', [EmployeeController::class, 'hierarchy']);
});

Route::middleware('permission:create employees')->group(function () {
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::post('/employees/import', [EmployeeController::class, 'import']);
});

Route::middleware('permission:edit employees')->group(function () {
    Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
    Route::post('/employees/bulk-update', [EmployeeController::class, 'bulkUpdate']);
});

Route::middleware('permission:delete employees')->delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

Route::middleware('permission:edit employees')->group(function () {
    Route::post('/employees/{employee}/face/enroll', [EmployeeFaceController::class, 'enroll']);
    Route::delete('/employees/{employee}/face', [EmployeeFaceController::class, 'destroyEnrollment']);
});

Route::middleware('permission:edit employees')->group(function () {
    Route::post('/employees/{employee}/qr/generate', [EmployeeQrController::class, 'generate']);
    Route::delete('/employees/{employee}/qr', [EmployeeQrController::class, 'destroy']);
});

Route::middleware('permission:edit employees')->group(function () {
    Route::post('/employees/{employee}/photo', [EmployeePhotoController::class, 'upload']);
    Route::delete('/employees/{employee}/photo', [EmployeePhotoController::class, 'destroy']);
});

Route::middleware('permission:edit employees')->post('/employees/{employee}/resend-invite', [EmployeeController::class, 'resendInvite']);