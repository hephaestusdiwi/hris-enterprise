<?php

namespace App\Modules\Expense\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Requests\StoreExpenseClaimAttachmentRequest;
use App\Modules\Expense\Services\ExpenseClaimService;

class ExpenseClaimAttachmentController extends Controller
{
    public function __construct(
        private ExpenseClaimService $expenseClaimService,
    ) {
    }

    public function store(StoreExpenseClaimAttachmentRequest $request, ExpenseClaim $expenseClaim)
    {
        $employee = $request->user()->employee;

        abort_unless(
            $employee?->id === $expenseClaim->employee_id
                || $request->user()->can('view expense claims'),
            403,
        );

        $attachments = collect($request->file('attachments'))
            ->map(fn ($file) => $this->expenseClaimService->storeAttachment($expenseClaim, $file))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Attachment berhasil diupload',
            'data' => $attachments,
        ], 201);
    }
}