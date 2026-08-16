<?php

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementAttachment;
use App\Modules\Announcement\Requests\StoreAnnouncementAttachmentRequest;
use Illuminate\Support\Facades\Storage;

/**
 * Mengikuti pola EmployeePhotoController: Storage::disk('public'), path
 * terstruktur per-entity. Bedanya di sini generic file (bukan cuma image),
 * jadi tidak ada resize/convert — simpan apa adanya.
 */
class AnnouncementAttachmentController extends Controller
{
    public function store(StoreAnnouncementAttachmentRequest $request, Announcement $announcement)
    {
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
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment dihapus.', 'data' => null]);
    }
}
