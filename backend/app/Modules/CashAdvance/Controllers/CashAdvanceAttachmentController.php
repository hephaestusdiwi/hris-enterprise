<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvanceAttachment;
use App\Modules\CashAdvance\Models\CashAdvanceRequest as CashAdvanceRequestModel;
use App\Modules\CashAdvance\Requests\StoreCashAdvanceAttachmentRequest;
use Illuminate\Support\Facades\Storage;

class CashAdvanceAttachmentController extends Controller
{
    public function store(StoreCashAdvanceAttachmentRequest $request, CashAdvanceRequestModel $cashAdvance)
    {
        $employee = $request->user()->employee;
        $isOwner = $employee && $cashAdvance->employee_id === $employee->id;

        abort_if(! $isOwner && ! $request->user()->can('view cash advances'), 403, 'Anda tidak berhak menambah attachment di request ini.');

        $attachments = [];

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("cash-advance-attachments/{$cashAdvance->employee_id}", 'public');

            $attachments[] = CashAdvanceAttachment::create([
                'cash_advance_request_id' => $cashAdvance->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Attachment berhasil ditambahkan', 'data' => $attachments], 201);
    }

    public function destroy(CashAdvanceRequestModel $cashAdvance, CashAdvanceAttachment $attachment)
    {
        abort_if($attachment->cash_advance_request_id !== $cashAdvance->id, 404);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment berhasil dihapus', 'data' => null]);
    }
}