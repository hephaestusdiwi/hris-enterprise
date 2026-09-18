<?php

namespace App\Modules\Grooming\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroomingStandardItem extends Model
{
    protected $fillable = [
        'grooming_standard_id',
        'name',
        'description',
        'mandatory',
        'requires_note_on_fail',
        'requires_photo',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mandatory' => 'boolean',
            'requires_note_on_fail' => 'boolean',
            'requires_photo' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(GroomingStandard::class, 'grooming_standard_id');
    }
}