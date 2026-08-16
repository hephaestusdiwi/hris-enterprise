<?php

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Requests\StoreAnnouncementRequest;
use App\Modules\Announcement\Requests\UpdateAnnouncementRequest;
use App\Modules\Announcement\Services\AnnouncementService;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $service)
    {
    }

    /**
     * Management list — search/filter category & status, BUKAN inbox
     * employee (itu di AnnouncementRecipientController::myAnnouncements).
     */
    public function index(Request $request)
    {
        $query = Announcement::query()->with(['category', 'createdBy'])->latest();

        if ($categoryId = $request->query('category_id')) {
            $query->where('announcement_category_id', $categoryId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where('title', 'ilike', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $query->paginate(15),
        ]);
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $announcement = $this->service->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Announcement (draft) berhasil dibuat.',
            'data' => $announcement,
        ], 201);
    }

    public function show(Announcement $announcement)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $announcement->load(['category', 'createdBy', 'targets', 'attachments']),
        ]);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        $announcement = $this->service->update($announcement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Announcement berhasil diperbarui.',
            'data' => $announcement,
        ]);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return response()->json(['success' => true, 'message' => 'Announcement dihapus.', 'data' => null]);
    }

    public function publish(Announcement $announcement)
    {
        $this->service->publish($announcement);

        return response()->json([
            'success' => true,
            'message' => 'Announcement berhasil dipublish.',
            'data' => $announcement->fresh(['recipients']),
        ]);
    }
}
