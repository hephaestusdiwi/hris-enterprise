<?php

namespace App\Modules\CompanyObligation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanyObligation\Models\CompanyObligation;
use App\Modules\CompanyObligation\Notifications\CompanyObligationReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app reminder TERBATAS di dalam module ini saja (bukan global
 * Inbox/Bell) -- baca langsung dari tabel `notifications` bawaan Laravel
 * (database channel), difilter ke type notification module ini.
 */
class CompanyObligationReminderController extends Controller
{
    public function indexMine(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->where('type', CompanyObligationReminderNotification::class)
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $notifications,
            'meta' => [
                'unread_count' => $request->user()->unreadNotifications()
                    ->where('type', CompanyObligationReminderNotification::class)
                    ->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()
            ->where('type', CompanyObligationReminderNotification::class)
            ->findOrFail($notification);

        $record->markAsRead();

        return response()->json(['success' => true, 'message' => 'Reminder ditandai sudah dibaca', 'data' => $record->fresh()]);
    }

    /**
     * Riwayat reminder yang sudah terkirim untuk 1 obligation (semua
     * recipient) -- buat HR/admin audit "reminder ini udah kekirim ke
     * siapa aja". Authorization ikut CompanyObligationPolicy::view().
     */
    public function history(CompanyObligation $companyObligation): JsonResponse
    {
        $companyObligation->load('recipients');
        $this->authorize('view', $companyObligation);

        $reminders = DatabaseNotification::query()
            ->where('type', CompanyObligationReminderNotification::class)
            ->where('data->company_obligation_id', $companyObligation->id)
            ->with('notifiable')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $reminders]);
    }
}