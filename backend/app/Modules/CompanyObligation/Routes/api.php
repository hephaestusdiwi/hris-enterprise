<?php

use App\Modules\CompanyObligation\Controllers\CompanyObligationController;
use App\Modules\CompanyObligation\Controllers\CompanyObligationRecipientController;
use App\Modules\CompanyObligation\Controllers\CompanyObligationReminderController;
use Illuminate\Support\Facades\Route;

// Self-service -- read-only, tanpa permission statis (obligation yang
// relevan buat user ini dicek dinamis lewat CompanyObligationScope).
Route::get('/my-company-obligations', [CompanyObligationController::class, 'indexMine']);

// In-app reminder module ini SAJA (bukan global Inbox/Bell) -- tanpa
// permission statis, semua user login boleh lihat reminder miliknya sendiri.
Route::get('/my-company-obligation-reminders', [CompanyObligationReminderController::class, 'indexMine']);
Route::post('/company-obligation-reminders/{notification}/read', [CompanyObligationReminderController::class, 'markAsRead']);

// Management -- permission jadi gerbang pertama, Policy mempersempit per-record.
Route::middleware('permission:view company obligations')
    ->get('/company-obligations', [CompanyObligationController::class, 'index']);

Route::middleware('permission:create company obligations')
    ->post('/company-obligations', [CompanyObligationController::class, 'store']);

// show/update/destroy TIDAK pakai middleware permission statis --
// CompanyObligationPolicy yang menentukan (view juga mengizinkan PIC &
// recipient eksplisit, bukan cuma pemegang permission).
Route::get('/company-obligations/{companyObligation}', [CompanyObligationController::class, 'show']);
Route::put('/company-obligations/{companyObligation}', [CompanyObligationController::class, 'update']);
Route::delete('/company-obligations/{companyObligation}', [CompanyObligationController::class, 'destroy']);

Route::get('/company-obligations/{companyObligation}/reminders', [CompanyObligationReminderController::class, 'history']);
Route::post('/company-obligations/{companyObligation}/recipients', [CompanyObligationRecipientController::class, 'store']);
Route::delete('/company-obligations/{companyObligation}/recipients/{recipient}', [CompanyObligationRecipientController::class, 'destroy']);