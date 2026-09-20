<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThrPolicy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'minimum_service_months', 'full_service_months',
        'include_in_bpjs_base', 'is_active', 'effective_date', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'minimum_service_months' => 'integer',
            'full_service_months' => 'integer',
            'include_in_bpjs_base' => 'boolean',
            'is_active' => 'boolean',
            'effective_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
