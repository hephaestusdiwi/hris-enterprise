<?php

use App\Modules\Announcement\Controllers\AnnouncementAttachmentController;
use App\Modules\Announcement\Controllers\AnnouncementCategoryController;
use App\Modules\Announcement\Controllers\AnnouncementController;
use App\Modules\Announcement\Controllers\AnnouncementRecipientController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Inbox pribadi — semua employee login boleh akses punya sendiri,
    // tidak butuh permission 'view announcements' (itu buat management list).
    Route::get('/my-announcements', [AnnouncementRecipientController::class, 'myAnnouncements']);
    Route::post('/announcements/{announcement}/read', [AnnouncementRecipientController::class, 'markRead']);

    Route::middleware('permission:view announcements')->group(function () {
        Route::get('/announcement-categories', [AnnouncementCategoryController::class, 'index']);
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
    });

    Route::middleware('permission:create announcements')->group(function () {
        Route::post('/announcement-categories', [AnnouncementCategoryController::class, 'store']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
    });

    Route::middleware('permission:edit announcements')->group(function () {
        Route::put('/announcement-categories/{announcementCategory}', [AnnouncementCategoryController::class, 'update']);
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::post('/announcements/{announcement}/attachments', [AnnouncementAttachmentController::class, 'store']);
        Route::delete('/announcements/{announcement}/attachments/{attachment}', [AnnouncementAttachmentController::class, 'destroy']);
    });

    Route::middleware('permission:publish announcements')->post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish']);
    Route::middleware('permission:delete announcements')->delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
    Route::middleware('permission:delete announcements')->delete('/announcement-categories/{announcementCategory}', [AnnouncementCategoryController::class, 'destroy']);
});
