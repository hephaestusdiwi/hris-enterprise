<?php

namespace App\Modules\Expense\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExpenseClaimAttachment extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}