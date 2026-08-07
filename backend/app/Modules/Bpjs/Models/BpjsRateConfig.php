<?php

namespace App\Modules\Bpjs\Models;

use App\Modules\Bpjs\Enums\BpjsProgram;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsRateConfig extends Model
{
    protected $fillable = [
        'company_id',
        'program',
        'effective_date',
        'is_active',
        'employee_rate_percentage',
        'employer_rate_percentage',
        'wage_base_cap',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'program' => BpjsProgram::class,
            'effective_date' => 'date',
            'is_active' => 'boolean',
            'employee_rate_percentage' => 'decimal:2',
            'employer_rate_percentage' => 'decimal:2',
            'wage_base_cap' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}