<?php

namespace App\Modules\Interview\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewStage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'sequence', 'is_active'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }
}