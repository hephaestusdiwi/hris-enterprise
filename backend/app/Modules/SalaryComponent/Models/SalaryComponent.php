<?php

namespace App\Modules\SalaryComponent\Models;

use App\Modules\Company\Models\Company;
use App\Modules\SalaryComponent\Enums\CalculationMethod;
use App\Modules\SalaryComponent\Enums\PercentageBase;
use App\Modules\SalaryComponent\Enums\SalaryComponentCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'category',
        'is_addition',
        'calculation_method',
        'amount',
        'percentage_value',
        'percentage_base',
        'is_taxable',
        'include_in_bpjs_base',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => SalaryComponentCategory::class,
            'is_addition' => 'boolean',
            'calculation_method' => CalculationMethod::class,
            'amount' => 'decimal:2',
            'percentage_value' => 'decimal:2',
            'percentage_base' => PercentageBase::class,
            'is_taxable' => 'boolean',
            'include_in_bpjs_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}