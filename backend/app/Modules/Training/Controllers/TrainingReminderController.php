<?php

namespace App\Modules\Training\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Models\TrainingSession;
use App\Modules\Training\Notifications\TrainingReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app reminder TERBATAS di dalam module ini saja (bukan global
 * Inbox/Bell) -- pola persis CompanyObligationReminderController.
 */
class TrainingReminderController extends Controller
{
    public function indexMine(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->where('type', TrainingReminderNotification::class)
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $notifications,
            'meta' => [
                'unread_count' => $request->user()->unreadNotifications()
                    ->where('type', TrainingReminderNotification::class)
                    ->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()
            ->where('type', TrainingReminderNotification::class)
            ->findOrFail($notification);

        $record->markAsRead();

        return response()->json(['success' => true, 'message' => 'Reminder ditandai sudah dibaca', 'data' => $record->fresh()]);
    }

    /**
     * Riwayat reminder yang sudah terkirim untuk 1 sesi (semua recipient)
     * -- buat HR/admin audit. Authorization ikut TrainingProgramPolicy::view()
     * lewat program induk sesi ini.
     */
    public function history(TrainingSession $session): JsonResponse
    {
        $program = $session->program()->with('recipients')->firstOrFail();
        $this->authorize('view', $program);

        $reminders = DatabaseNotification::query()
            ->where('type', TrainingReminderNotification::class)
            ->where('data->training_session_id', $session->id)
            ->with('notifiable')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $reminders]);
    }
}