<?php

namespace App\Modules\Pph21\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Pph21\Enums\TaxMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTaxSetting extends Model
{
    protected $fillable = [
        'company_id',
        'default_tax_method',
        'no_npwp_surcharge_percentage',
        'position_cost_percentage',
        'position_cost_monthly_cap',
        'position_cost_annual_cap',
    ];

    protected function casts(): array
    {
        return [
        'default_max_method' => TaxMethod::class,
        'no_npwp_surcharge_percentage' => 'decimal:2',
        'position_cost_percentage' => 'decimal:2',
        'position_cost_monthly_cap' => 'decimal:2',
        'position_cost_annual_cap' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}