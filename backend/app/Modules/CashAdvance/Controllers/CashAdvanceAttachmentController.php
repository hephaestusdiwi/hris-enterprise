<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvanceAttachment;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceAttachmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CashAdvanceAttachmentController extends Controller
{
    public function store(
        StoreCashAdvanceAttachmentRequest $request,
        CashAdvanceRequest $cashAdvance,
    ) {
        $employee = $request->user()->employee;

        abort_unless(
            $employee?->id === $cashAdvance->employee_id
                || $request->user()->can('view cash advances'),
            403,
        );

        $file = $request->file('file');

        $path = $file->store(
            "cash-advance-attachments/{$cashAdvance->employee_id}",
            'public',
        );

        $attachment = $cashAdvance->attachments()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attachment berhasil diupload',
            'data' => $attachment,
        ], 201);
    }

    public function destroy(
        Request $request,
        CashAdvanceAttachment $attachment,
    ) {
        $cashAdvance = $attachment->request;

        $employee = $request->user()->employee;

        abort_unless(
            $employee?->id === $cashAdvance->employee_id
                || $request->user()->can('view cash advances'),
            403,
        );

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment berhasil dihapus',
            'data' => null,
        ]);
    }
}