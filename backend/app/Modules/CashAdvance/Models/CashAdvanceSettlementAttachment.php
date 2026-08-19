<?php

namespace App\Modules\CashAdvance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CashAdvanceSettlementAttachment extends Model
{
    protected $fillable = [
        'cash_advance_settlement_id',
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

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceSettlement::class, 'cash_advance_settlement_id');
    }
}