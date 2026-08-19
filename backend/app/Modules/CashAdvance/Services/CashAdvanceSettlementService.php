<?php

namespace App\Modules\CashAdvance\Services;

use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementStatus;
use App\Modules\CashAdvance\Exceptions\CashAdvanceValidationException;
use App\Modules\CashAdvance\Models\CashAdvanceCategory;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Models\CashAdvanceSettlement;
use App\Modules\CashAdvance\Models\CashAdvanceSettlementAttachment;
use App\Modules\CashAdvance\Models\CashAdvanceSettlementItem;
use App\Modules\CashAdvance\Support\CashAdvanceMath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CashAdvanceSettlementService
{
    public function __construct(private CashAdvanceSettlementApprovalService $approvalService)
    {
    }

    /**
     * @param array{notes?: ?string,
     *              items: array<int, array{cash_advance_category_id: int, cash_advance_request_item_id?: ?int,
     *                                       description: string, actual_amount: string|float, returned_amount?: string|float|null}>,
     *              attachments?: array<int, UploadedFile>} $data
     */
    public function submit(CashAdvanceRequest $request, array $data): CashAdvanceSettlement
    {
        if ($request->status !== CashAdvanceRequestStatus::NeedSettlement) {
            throw new CashAdvanceValidationException('Request ini tidak dalam status Perlu Settlement (mungkin belum di-disburse atau sudah ada settlement diproses).');
        }

        if (empty($data['items'])) {
            throw new CashAdvanceValidationException('Minimal satu item settlement harus diisi.');
        }

        $requestItemIds = $request->items()->pluck('id')->all();

        $totalActual = '0.00';
        $totalReturned = '0.00';
        $resolvedItems = [];

        foreach ($data['items'] as $item) {
            $category = CashAdvanceCategory::find($item['cash_advance_category_id']);

            if (! $category) {
                throw new CashAdvanceValidationException('Ada kategori settlement yang tidak valid.');
            }

            $requestItemId = $item['cash_advance_request_item_id'] ?? null;

            if ($requestItemId !== null && ! in_array((int) $requestItemId, $requestItemIds, true)) {
                throw new CashAdvanceValidationException('Ada item settlement yang tidak terkait dengan request Cash Advance ini.');
            }

            $actual = (string) $item['actual_amount'];
            $returned = (string) ($item['returned_amount'] ?? '0');

            if (! CashAdvanceMath::gte($actual, '0')) {
                throw new CashAdvanceValidationException('Actual amount tidak boleh negatif.');
            }

            if (! CashAdvanceMath::gte($returned, '0')) {
                throw new CashAdvanceValidationException('Returned amount tidak boleh negatif.');
            }

            $totalActual = CashAdvanceMath::add($totalActual, $actual);
            $totalReturned = CashAdvanceMath::add($totalReturned, $returned);

            $resolvedItems[] = [
                'request_item_id' => $requestItemId,
                'category_id' => $category->id,
                'description' => $item['description'],
                'actual_amount' => $actual,
                'returned_amount' => $returned,
            ];
        }

        return DB::transaction(function () use ($request, $data, $resolvedItems, $totalActual, $totalReturned) {
            $settlement = CashAdvanceSettlement::create([
                'cash_advance_request_id' => $request->id,
                'total_actual_amount' => $totalActual,
                'total_returned_amount' => $totalReturned,
                'notes' => $data['notes'] ?? null,
                'status' => CashAdvanceSettlementStatus::Pending->value,
                'submitted_at' => now(),
            ]);

            foreach ($resolvedItems as $item) {
                CashAdvanceSettlementItem::create([
                    'cash_advance_settlement_id' => $settlement->id,
                    'cash_advance_request_item_id' => $item['request_item_id'],
                    'cash_advance_category_id' => $item['category_id'],
                    'description' => $item['description'],
                    'actual_amount' => $item['actual_amount'],
                    'returned_amount' => $item['returned_amount'],
                ]);
            }

            foreach ($data['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeAttachment($settlement, $file);
                }
            }

            $request->update(['status' => CashAdvanceRequestStatus::SettlementOnReview->value]);

            $this->approvalService->initiate($settlement);

            return $settlement->fresh(['items.category', 'attachments']);
        });
    }

    private function storeAttachment(CashAdvanceSettlement $settlement, UploadedFile $file): void
    {
        $path = $file->store("cash-advance-settlement-attachments/{$settlement->cash_advance_request_id}", 'public');

        CashAdvanceSettlementAttachment::create([
            'cash_advance_settlement_id' => $settlement->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);
    }
}