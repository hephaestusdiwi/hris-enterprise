<?php

namespace App\Modules\Bpjs\Models;

use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsCompanyRegistration extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'npp_number',
        'risk_class',
        'label',
        'effective_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'risk_class' => 'integer',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}