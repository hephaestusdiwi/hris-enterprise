<?php

namespace App\Modules\Pph21\Models;

use App\Modules\Pph21\Enums\PtkpStatus;
use Illuminate\Database\Eloquent\Model;

class PtkpConfig extends Model
{
    protected $fillable = ['ptkp_status', 'effective_date', 'is_active', 'annual_amount'];

    protected function casts(): array
    {
        return [
            'ptkp_status' => PtkpStatus::class,
            'effective_date' => 'date',
            'is_active' => 'boolean',
            'annual_amount' => 'decimal:2',
        ];
    }
}