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

        // PENTING: response harus selalu berbentuk paginator (data.data =
        // array), sama seperti kasus employee beneran punya recipient —
        // jangan return flat array [] di sini, itu bikin shape response
        // beda-beda tergantung kondisi dan bikin frontend (yang selalu baca
        // response.data.data.data, konsisten dengan pola paginate() di
        // seluruh project ini) crash baca .data dari array kosong.
        if (! $employee) {
            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
            ]);
        }

        $recipients = AnnouncementRecipient::where('employee_id', $employee->id)
            ->with(['announcement.category', 'announcement.attachments', 'announcement.createdBy.employee'])
            ->latest('id')
            ->paginate(15);

        // SENGAJA reshape manual, bukan langsung serialize raw model:
        // createdBy itu relasi ke User (bisa bawa password hash/remember_token
        // ikut ke-load), jadi field yang keluar ke response HARUS dibatasi
        // eksplisit ke {id, name, photo_url} — bukan cuma diserahkan ke
        // $hidden di User model. Selain 'created_by', shape lainnya
        // (announcement.category, announcement.attachments, dst) TETAP
        // persis seperti sebelumnya lewat toArray().
        $recipients->through(function (AnnouncementRecipient $recipient) {
            $data = $recipient->toArray();

            $creator = $recipient->announcement->createdBy;
            $data['announcement']['created_by'] = $creator ? [
                'id' => $creator->id,
                'name' => $creator->name,
                'photo_url' => $creator->employee?->photo_url,
            ] : null;

            return $data;
        });

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $recipients]);
    }

    public function unreadCount(Request $request)
    {
        $employee = $request->user()->employee;

        $count = $employee
            ? AnnouncementRecipient::where('employee_id', $employee->id)->whereNull('read_at')->count()
            : 0;

        return response()->json(['success' => true, 'message' => 'OK', 'data' => ['unread_count' => $count]]);
    }

    /**
     * Detail announcement dari sisi employee — object-level check-nya
     * "apakah dia recipient announcement ini", BUKAN permission
     * 'view announcements' (itu buat management list). Kalau bukan
     * recipient, 403 — employee tidak bisa baca punya orang lain walau
     * tau ID-nya.
     */
    public function show(Request $request, Announcement $announcement)
    {
        $employee = $request->user()->employee;

        $recipient = $employee
            ? AnnouncementRecipient::where('announcement_id', $announcement->id)->where('employee_id', $employee->id)->first()
            : null;

        if (! $recipient) {
            abort(403, 'Kamu bukan recipient announcement ini.');
        }

        $announcement->load(['category', 'attachments', 'createdBy.employee']);

        $data = $announcement->toArray();
        $creator = $announcement->createdBy;
        $data['created_by'] = $creator ? [
            'id' => $creator->id,
            'name' => $creator->name,
            'photo_url' => $creator->employee?->photo_url,
        ] : null;

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'announcement' => $data,
                'read_at' => $recipient->read_at,
            ],
        ]);
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