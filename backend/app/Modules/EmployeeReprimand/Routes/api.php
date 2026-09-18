<?php

use App\Modules\EmployeeReprimand\Controllers\EmployeeReprimandController;
use Illuminate\Support\Facades\Route;

// Self-service -- READ-ONLY, tanpa permission. Employee TIDAK BISA
// create/edit/void reprimand-nya sendiri (murni wewenang HR/Admin).
Route::get('/my-reprimands', [EmployeeReprimandController::class, 'indexMine']);

// Admin -- permission per-resource baru.
Route::middleware('permission:view employee reprimands')->group(function () {
    Route::get('/employees/{employee}/reprimands', [EmployeeReprimandController::class, 'indexForEmployee']);
    Route::get('/employees/{employee}/reprimands/{reprimand}', [EmployeeReprimandController::class, 'showForEmployee']);
});

Route::middleware('permission:create employee reprimands')
    ->post('/employees/{employee}/reprimands', [EmployeeReprimandController::class, 'storeForEmployee']);

Route::middleware('permission:edit employee reprimands')->group(function () {
    // POST (bukan PUT) karena bisa bawa file attachment -- pola sama
    // seperti /my-educations/{education} & /employees/{employee}/photo.
    Route::post('/employees/{employee}/reprimands/{reprimand}', [EmployeeReprimandController::class, 'updateForEmployee']);
});

Route::middleware('permission:delete employee reprimands')
    ->post('/employees/{employee}/reprimands/{reprimand}/void', [EmployeeReprimandController::class, 'voidForEmployee']);