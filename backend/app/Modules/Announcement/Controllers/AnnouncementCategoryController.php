<?php

namespace App\Modules\Announcement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Models\AnnouncementCategory;
use App\Modules\Announcement\Requests\StoreAnnouncementCategoryRequest;
use App\Modules\Announcement\Requests\UpdateAnnouncementCategoryRequest;

class AnnouncementCategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => AnnouncementCategory::query()->orderBy('name')->paginate(50),
        ]);
    }

    public function store(StoreAnnouncementCategoryRequest $request)
    {
        $category = AnnouncementCategory::create($request->validated());

        return response()->json(['success' => true, 'message' => 'Category dibuat.', 'data' => $category], 201);
    }

    public function update(UpdateAnnouncementCategoryRequest $request, AnnouncementCategory $announcementCategory)
    {
        $announcementCategory->update($request->validated());

        return response()->json(['success' => true, 'message' => 'Category diperbarui.', 'data' => $announcementCategory]);
    }

    public function destroy(AnnouncementCategory $announcementCategory)
    {
        $announcementCategory->delete();

        return response()->json(['success' => true, 'message' => 'Category dihapus.', 'data' => null]);
    }
}
