<?php

namespace App\Modules\CashAdvance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CashAdvanceAttachment extends Model
{
    protected $fillable = [
        'cash_advance_request_id',
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

    public function request(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceRequest::class, 'cash_advance_request_id');
    }
}