<?php

namespace App\Modules\LeaveType\Models;

use App\Modules\Company\Models\Company;
use App\Modules\LeaveType\Enums\GenderRestriction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'color',
        'is_paid',
        'max_days_per_year',
        'min_service_months',
        'requires_attachment',
        'gender_restriction',
        'carry_over_allowed',
        'carry_over_max_days',
        'carry_over_expiry_month',
        'requires_approval',
        'allow_half_day',
        'allow_hourly',
        'requires_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'max_days_per_year' => 'integer',
            'min_service_months' => 'integer',
            'requires_attachment' => 'boolean',
            'gender_restriction' => GenderRestriction::class,
            'carry_over_allowed' => 'boolean',
            'carry_over_max_days' => 'integer',
            'carry_over_expiry_month' => 'integer',
            'requires_approval' => 'boolean',
            'allow_half_day' => 'boolean',
            'allow_hourly' => 'boolean',
            'requires_balance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}