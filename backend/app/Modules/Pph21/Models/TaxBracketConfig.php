<?php

namespace App\Modules\Pph21\Models;

use Illuminate\Database\Eloquent\Model;

class TaxBracketConfig extends Model
{
    protected $fillable = ['effective_date', 'is_active', 'income_from', 'income_to', 'rate_percentage'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
            'income_from' => 'decimal:2',
            'income_to' => 'decimal:2',
            'rate_percentage' => 'decimal:2',
        ];
    }
}