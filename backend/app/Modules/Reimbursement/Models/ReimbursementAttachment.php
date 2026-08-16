<?php

namespace App\Modules\Reimbursement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementAttachment extends Model
{
    protected $fillable = [
        'reimbursement_request_id',
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
        return \Illuminate\Support\Facades\Storage::disk('public')
            ->url($this->file_path);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ReimbursementRequest::class,
            'reimbursement_request_id'
        );
    }
}