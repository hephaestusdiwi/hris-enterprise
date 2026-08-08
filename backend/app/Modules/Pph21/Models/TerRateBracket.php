<?php

namespace App\Modules\Pph21\Models;

use App\Modules\Pph21\Enums\TerCategory;
use Illuminate\Database\Eloquent\Model;

class TerRateBracket extends Model
{
    protected $fillable = ['category', 'effective_date', 'is_active', 'income_from', 'income_to', 'rate_percentage'];

    protected function casts(): array
    {
        return [
            'category' => TerCategory::class,
            'effective_date' => 'date',
            'is_active' => 'boolean',
            'income_from' => 'decimal:2',
            'income_to' => 'decimal:2',
            'rate_percentage' => 'decimal:2',
        ];
    }
}