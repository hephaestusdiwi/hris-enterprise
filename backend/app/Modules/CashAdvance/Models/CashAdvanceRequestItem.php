<?php

namespace App\Modules\CashAdvance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceRequestItem extends Model
{
    protected $fillable = [
        'cash_advance_request_id',
        'cash_advance_category_id',
        'name',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceRequest::class, 'cash_advance_request_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceCategory::class, 'cash_advance_category_id');
    }
}