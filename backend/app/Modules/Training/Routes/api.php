<?php

use App\Modules\Training\Controllers\TrainingCategoryController;
use App\Modules\Training\Controllers\TrainingParticipantController;
use App\Modules\Training\Controllers\TrainingProgramController;
use App\Modules\Training\Controllers\TrainingRecipientController;
use App\Modules\Training\Controllers\TrainingReminderController;
use App\Modules\Training\Controllers\TrainingSessionController;
use Illuminate\Support\Facades\Route;

// Self-service -- read-only, tanpa permission statis (program yang
// relevan buat user ini dicek dinamis lewat TrainingScope).
Route::get('/my-trainings', [TrainingProgramController::class, 'indexMine']);

// In-app reminder module ini SAJA (bukan global Inbox/Bell).
Route::get('/my-training-reminders', [TrainingReminderController::class, 'indexMine']);
Route::post('/training-reminders/{notification}/read', [TrainingReminderController::class, 'markAsRead']);

// Master data -- Training Category.
Route::middleware('permission:view training categories')
    ->get('/training-categories', [TrainingCategoryController::class, 'index']);
Route::middleware('permission:create training categories')
    ->post('/training-categories', [TrainingCategoryController::class, 'store']);
Route::middleware('permission:view training categories')
    ->get('/training-categories/{trainingCategory}', [TrainingCategoryController::class, 'show']);
Route::middleware('permission:edit training categories')
    ->put('/training-categories/{trainingCategory}', [TrainingCategoryController::class, 'update']);
Route::middleware('permission:delete training categories')
    ->delete('/training-categories/{trainingCategory}', [TrainingCategoryController::class, 'destroy']);

// Management Training Program -- permission jadi gerbang pertama, Policy
// mempersempit per-record (lihat TrainingProgramPolicy).
Route::middleware('permission:view trainings')
    ->get('/training-programs', [TrainingProgramController::class, 'index']);
Route::middleware('permission:create trainings')
    ->post('/training-programs', [TrainingProgramController::class, 'store']);

// show/update/destroy TIDAK pakai middleware permission statis --
// TrainingProgramPolicy yang menentukan (view juga mengizinkan PIC &
// peserta, bukan cuma pemegang permission).
Route::get('/training-programs/{trainingProgram}', [TrainingProgramController::class, 'show']);
Route::put('/training-programs/{trainingProgram}', [TrainingProgramController::class, 'update']);
Route::delete('/training-programs/{trainingProgram}', [TrainingProgramController::class, 'destroy']);

// Sesi/batch di bawah 1 program.
Route::post('/training-programs/{trainingProgram}/sessions', [TrainingSessionController::class, 'store']);
Route::put('/training-programs/{trainingProgram}/sessions/{session}', [TrainingSessionController::class, 'update']);
Route::delete('/training-programs/{trainingProgram}/sessions/{session}', [TrainingSessionController::class, 'destroy']);

// Peserta di bawah 1 sesi.
Route::post('/training-programs/{trainingProgram}/sessions/{session}/participants', [TrainingParticipantController::class, 'store']);
Route::put('/training-programs/{trainingProgram}/sessions/{session}/participants/{participant}', [TrainingParticipantController::class, 'update']);
Route::delete('/training-programs/{trainingProgram}/sessions/{session}/participants/{participant}', [TrainingParticipantController::class, 'destroy']);

// Recipient tambahan di level program.
Route::post('/training-programs/{trainingProgram}/recipients', [TrainingRecipientController::class, 'store']);
Route::delete('/training-programs/{trainingProgram}/recipients/{recipient}', [TrainingRecipientController::class, 'destroy']);

// Riwayat reminder per sesi (buat audit HR/admin).
Route::get('/training-sessions/{session}/reminders', [TrainingReminderController::class, 'history']);