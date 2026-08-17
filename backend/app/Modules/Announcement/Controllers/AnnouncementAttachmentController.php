<?php

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Exceptions\AnnouncementException;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementAttachment;
use App\Modules\Announcement\Requests\StoreAnnouncementAttachmentRequest;
use App\Modules\Announcement\Services\AnnouncementService;
use Illuminate\Support\Facades\Storage;

/**
 * Mengikuti pola EmployeePhotoController: Storage::disk('public'), path
 * terstruktur per-entity. Bedanya di sini generic file (bukan cuma image),
 * jadi tidak ada resize/convert — simpan apa adanya.
 */
class AnnouncementAttachmentController extends Controller
{
    public function __construct(private AnnouncementService $service)
    {
    }

    public function store(StoreAnnouncementAttachmentRequest $request, Announcement $announcement)
    {
        // Announcement Published = locked total, termasuk attachment.
        // Sama seperti update() — satu titik enforcement di Service.
        $this->service->assertIsDraft($announcement);

        $file = $request->file('file');
        $path = $file->store("announcements/{$announcement->id}", 'public');

        $attachment = AnnouncementAttachment::create([
            'announcement_id' => $announcement->id,
            'disk' => 'public',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['success' => true, 'message' => 'Attachment berhasil diupload.', 'data' => $attachment], 201);
    }

    public function destroy(Announcement $announcement, AnnouncementAttachment $attachment)
    {
        $this->service->assertIsDraft($announcement);

        // Ownership check: attachment di URL harus BENERAN milik announcement
        // di URL yang sama — kalau tidak, tolak SEBELUM nyentuh storage
        // maupun DB record-nya sama sekali (bukan cuma skip delete-nya).
        if ($attachment->announcement_id !== $announcement->id) {
            throw new AnnouncementException('Attachment ini bukan milik announcement yang dimaksud.');
        }

        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment dihapus.', 'data' => null]);
    }
}
