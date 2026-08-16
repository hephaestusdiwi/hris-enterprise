<?php

namespace App\Modules\Reimbursement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementRequestItem extends Model
{
    protected $fillable = [
        'reimbursement_request_id',
        'reimbursement_benefit_id',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementRequest::class,
            'reimbursement_request_id'
        );
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementBenefit::class,
            'reimbursement_benefit_id'
        );
    }
}