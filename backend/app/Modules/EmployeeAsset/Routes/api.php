<?php

use App\Modules\EmployeeAsset\Controllers\EmployeeAssetController;
use Illuminate\Support\Facades\Route;

// Self-service -- read-only, tanpa permission. Employee TIDAK bisa
// menambah/menghapus aset sendiri (beda dari /my-documents).
Route::get('/my-assets', [EmployeeAssetController::class, 'indexMine']);

// Admin -- permission per-resource baru.
Route::middleware('permission:view employee assets')
    ->get('/employees/{employee}/assets', [EmployeeAssetController::class, 'indexForEmployee']);

Route::middleware('permission:create employee assets')
    ->post('/employees/{employee}/assets', [EmployeeAssetController::class, 'storeForEmployee']);

Route::middleware('permission:edit employee assets')
    ->put('/employees/{employee}/assets/{asset}', [EmployeeAssetController::class, 'updateForEmployee']);

Route::middleware('permission:delete employee assets')
    ->delete('/employees/{employee}/assets/{asset}', [EmployeeAssetController::class, 'destroyForEmployee']);