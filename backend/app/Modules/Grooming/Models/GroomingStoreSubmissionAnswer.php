<?php

namespace App\Modules\Grooming\Models;

use App\Modules\Grooming\Enums\GroomingResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroomingStoreSubmissionAnswer extends Model
{
    protected $fillable = [
        'grooming_store_submission_id',
        'grooming_standard_item_id',
        'result',
        'note',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'result' => GroomingResult::class,
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GroomingStoreSubmission::class, 'grooming_store_submission_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GroomingStandardItem::class, 'grooming_standard_item_id');
    }
}