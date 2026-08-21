<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvanceAttachment;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceAttachmentRequest;
use App\Modules\CashAdvance\Services\CashAdvanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CashAdvanceAttachmentController extends Controller
{
    public function __construct(
        private CashAdvanceService $cashAdvanceService,
    ) {
    }

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

        // Contract-nya (lihat StoreCashAdvanceAttachmentRequest) adalah
        // multi-file lewat key `attachments` (array, min 1 max 5) --
        // sebelumnya controller salah baca key `file` (singular) yang
        // memang tidak pernah divalidasi, jadi $file selalu null.
        $attachments = collect($request->file('attachments'))
            ->map(fn ($file) => $this->cashAdvanceService->storeAttachment($cashAdvance, $file))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Attachment berhasil diupload',
            'data' => $attachments,
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