<?php

namespace App\Modules\Reimbursement\Models;

use App\Modules\Reimbursement\Enums\ReimbursementBalanceTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementBalanceTransaction extends Model
{
    protected $fillable = [
        'reimbursement_balance_id',
        'type',
        'amount',
        'running_balance',
        'reimbursement_request_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReimbursementBalanceTransactionType::class,
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementBalance::class,
            'reimbursement_balance_id'
        );
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementRequest::class,
            'reimbursement_request_id'
        );
    }
}