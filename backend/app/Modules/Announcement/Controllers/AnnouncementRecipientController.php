<?php

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementRecipient;
use App\Modules\Announcement\Services\AnnouncementService;
use Illuminate\Http\Request;

/**
 * Inbox pribadi employee — daftar announcement yang DIA jadi recipient-nya
 * + read/unread state. Scoping otomatis lewat tabel announcement_recipients
 * (cuma baris yang punya employee_id dia) — tidak butuh Policy/Scope
 * terpisah, tabel recipient itu sendiri sudah jadi mekanisme scope-nya.
 *
 * SENGAJA query AnnouncementRecipient langsung (bukan nambah relasi baru
 * di Employee model) — Employee module tidak boleh "tahu" soal Announcement,
 * konsisten dengan pola dependency satu arah yang sudah dipakai project ini
 * (EmployeeSalary/Bpjs/Pph21 reach IN ke Employee lewat employee_id, bukan
 * sebaliknya).
 */
class AnnouncementRecipientController extends Controller
{
    public function __construct(private AnnouncementService $service)
    {
    }

    public function myAnnouncements(Request $request)
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => []]);
        }

        $recipients = AnnouncementRecipient::where('employee_id', $employee->id)
            ->with(['announcement.category', 'announcement.attachments'])
            ->latest('id')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $recipients]);
    }

    public function markRead(Request $request, Announcement $announcement)
    {
        $employee = $request->user()->employee;

        if ($employee) {
            $this->service->markAsRead($announcement, $employee);
        }

        return response()->json(['success' => true, 'message' => 'Ditandai sudah dibaca.', 'data' => null]);
    }
}
