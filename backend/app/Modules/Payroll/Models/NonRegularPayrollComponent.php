<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Payroll\Enums\NonRegularComponentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonRegularPayrollComponent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'category',
        'is_addition', 'is_taxable', 'include_in_bpjs_base', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => NonRegularComponentCategory::class,
            'is_addition' => 'boolean',
            'is_taxable' => 'boolean',
            'include_in_bpjs_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Arah efektif komponen ini: pakai is_addition eksplisit kalau diisi,
     * kalau tidak fallback ke default kategori.
     */
    public function resolvedIsAddition(): ?bool
    {
        return $this->is_addition ?? $this->category->defaultIsAddition();
    }
}
