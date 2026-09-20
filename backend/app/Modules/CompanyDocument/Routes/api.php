<?php

use App\Modules\CompanyDocument\Controllers\CompanyDocumentController;
use Illuminate\Support\Facades\Route;

// Self-service -- read-only, tanpa permission (dokumen milik/terkait diri sendiri).
Route::get('/my-company-documents', [CompanyDocumentController::class, 'indexMine']);

// Company-wide (public, employee_id null) -- semua kategori di sini
// PUBLIC_CATEGORIES aja (dienforce di StoreCompanyDocumentRequest), jadi
// permission-nya statis lewat middleware seperti biasa.
Route::middleware('permission:view company documents')
    ->get('/company-documents', [CompanyDocumentController::class, 'index']);

Route::middleware('permission:create company documents')
    ->post('/company-documents', [CompanyDocumentController::class, 'store']);

// update/destroy company-wide TIDAK pakai middleware permission statis --
// dicek dinamis di controller (CompanyDocument::managePermissionFor)
// berdasarkan kategori dokumen yang bersangkutan.
Route::post('/company-documents/{document}', [CompanyDocumentController::class, 'update']);
Route::delete('/company-documents/{document}', [CompanyDocumentController::class, 'destroy']);

// Employee-scoped (admin) -- bisa kategori public-with-context ATAU
// private. Permission SELALU dicek dinamis di controller karena 1
// endpoint menangani kategori dengan permission manage yang beda-beda
// (lihat CompanyDocumentController::authorizeManage).
Route::get('/employees/{employee}/company-documents', [CompanyDocumentController::class, 'indexForEmployee']);
Route::post('/employees/{employee}/company-documents', [CompanyDocumentController::class, 'storeForEmployee']);
Route::post('/employees/{employee}/company-documents/{document}', [CompanyDocumentController::class, 'updateForEmployee']);
Route::delete('/employees/{employee}/company-documents/{document}', [CompanyDocumentController::class, 'destroyForEmployee']);