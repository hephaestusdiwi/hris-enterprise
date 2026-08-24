<?php

namespace App\Modules\Expense\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Exceptions\ExpenseClaimValidationException;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpenseClaimAttachment;
use App\Modules\Expense\Models\ExpenseSubcategory;
use App\Modules\Expense\Support\ExpenseClaimMath;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ExpenseClaimService
{
    public function __construct(
        private ExpensePolicyAssignmentResolver $assignmentResolver,
        private ExpenseClaimApprovalService $approvalService,
    ) {
    }

    /**
     * @param array{
     *     expense_category_id: int,
     *     expense_subcategory_id?: ?int,
     *     expense_date: string,
     *     amount: string|float,
     *     description?: ?string,
     *     attachments?: array<int, UploadedFile>
     * } $data
     */
    public function submit(Employee $employee, array $data): ExpenseClaim
    {
        $expenseDate = Carbon::parse($data['expense_date']);

        // Policy resolution WAJIB berdasarkan expense_date, bukan tanggal
        // submit -- keputusan terkunci STEP 4A. Tidak ada fallback ke
        // policy lain kalau tidak ada assignment aktif.
        $assignment = $this->assignmentResolver->resolveActiveAssignment(
            $employee,
            $expenseDate
        );

        if (! $assignment) {
            throw new ExpenseClaimValidationException(
                'Tidak ada Expense Policy yang aktif untuk employee ini pada tanggal tersebut.'
            );
        }

        $policy = $assignment->policy;

        // Effectiveness policy harus dievaluasi terhadap expense_date,
        // bukan tanggal submit / hari ini.
        if (! $policy->isCurrentlyEffective($expenseDate)) {
            throw new ExpenseClaimValidationException(
                'Expense Policy yang berlaku sudah tidak aktif atau kedaluwarsa pada tanggal tersebut.'
            );
        }

        $category = $policy->categories->firstWhere(
            'id',
            $data['expense_category_id']
        );

        if (! $category) {
            throw new ExpenseClaimValidationException(
                'Kategori ini tidak diizinkan oleh Expense Policy yang berlaku.'
            );
        }

        $subcategoryId = $data['expense_subcategory_id'] ?? null;

        if ($subcategoryId) {
            $subcategory = ExpenseSubcategory::find($subcategoryId);

            if (
                ! $subcategory
                || (int) $subcategory->expense_category_id !== (int) $category->id
            ) {
                throw new ExpenseClaimValidationException(
                    'Subcategory yang dipilih tidak termasuk dalam kategori ini.'
                );
            }
        }

        $amount = (string) $data['amount'];
        $limitAmount = $category->pivot->limit_amount;

        // limit_amount NULL = unlimited. Semantic-nya MAXIMUM PER CLAIM
        // (bukan aggregate) -- keputusan terkunci STEP 4A, tidak ada
        // ledger/running-balance yang dicek di sini.
        if (
            $limitAmount !== null
            && ! ExpenseClaimMath::gte((string) $limitAmount, $amount)
        ) {
            throw new ExpenseClaimValidationException(
                'Amount melebihi limit kategori ini pada Expense Policy yang berlaku.'
            );
        }

        return DB::transaction(function () use (
            $employee,
            $assignment,
            $category,
            $subcategoryId,
            $expenseDate,
            $amount,
            $data
        ) {
            $claim = ExpenseClaim::create([
                'employee_id' => $employee->id,
                'expense_policy_assignment_id' => $assignment->id,
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $subcategoryId,
                'expense_date' => $expenseDate->toDateString(),
                'amount' => $amount,
                'description' => $data['description'] ?? null,
                'status' => ExpenseClaimStatus::Pending->value,
            ]);

            foreach ($data['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeAttachment($claim, $file);
                }
            }

            $this->approvalService->initiate($claim);

            return $claim->fresh([
                'category',
                'subcategory',
                'attachments',
            ]);
        });
    }

    public function cancel(ExpenseClaim $claim, string $reason): ExpenseClaim
    {
        if (
            ! in_array(
                $claim->status,
                [
                    ExpenseClaimStatus::Pending,
                    ExpenseClaimStatus::Approved,
                ],
                true
            )
        ) {
            throw new ExpenseClaimValidationException(
                'Claim berstatus ini tidak bisa dibatalkan.'
            );
        }

        if ($claim->paid_at) {
            throw new ExpenseClaimValidationException(
                'Claim yang sudah dibayar tidak bisa dibatalkan lagi.'
            );
        }

        return DB::transaction(function () use ($claim, $reason) {
            $this->approvalService->cancelApprovalIfAny($claim);

            $claim->update([
                'status' => ExpenseClaimStatus::Cancelled->value,
                'decided_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $claim->fresh();
        });
    }

    /**
     * Public karena juga dipakai ExpenseClaimAttachmentController untuk
     * menambah attachment ke claim yang sudah ada -- satu tempat
     * penyimpanan logic, tidak diduplikasi (pola CashAdvanceService).
     */
    public function storeAttachment(
        ExpenseClaim $claim,
        UploadedFile $file
    ): ExpenseClaimAttachment {
        $path = $file->store(
            "expense-claim-attachments/{$claim->employee_id}",
            'public'
        );

        return ExpenseClaimAttachment::create([
            'expense_claim_id' => $claim->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);
    }
}