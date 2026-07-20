<?php

namespace App\Modules\Shift\Models;

use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'is_overnight',
        'break_start_time',
        'break_end_time',
        'late_tolerance_minutes',
        'check_in_before_minutes',
        'check_out_after_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_overnight' => 'boolean',
            'is_active' => 'boolean',
            'late_tolerance_minutes' => 'integer',
            'check_in_before_minutes' => 'integer',
            'check_out_after_minutes' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}