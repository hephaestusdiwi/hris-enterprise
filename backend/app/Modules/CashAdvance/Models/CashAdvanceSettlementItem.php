<?php

namespace App\Modules\CashAdvance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceSettlementItem extends Model
{
    protected $fillable = [
        'cash_advance_settlement_id',
        'cash_advance_request_item_id',
        'cash_advance_category_id',
        'description',
        'actual_amount',
        'returned_amount',
    ];

    protected function casts(): array
    {
        return [
            'actual_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceSettlement::class, 'cash_advance_settlement_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceRequestItem::class, 'cash_advance_request_item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceCategory::class, 'cash_advance_category_id');
    }
}