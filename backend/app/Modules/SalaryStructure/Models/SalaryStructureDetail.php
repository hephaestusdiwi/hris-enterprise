<?php

namespace App\Modules\SalaryStructure\Models;

use App\Modules\SalaryComponent\Enums\PercentageBase;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructureDetail extends Model
{
    protected $fillable = [
        'salary_structure_id',
        'salary_component_id',
        'override_amount',
        'override_percentage_value',
        'override_percentage_base',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'override_amount' => 'decimal:2',
            'override_percentage_value' => 'decimal:2',
            'override_percentage_base' => PercentageBase::class,
        ];
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    /**
     * Nilai efektif yang berlaku (override kalau ada, fallback ke nilai global component).
     */
    public function effectiveAmount(): ?string
    {
        return $this->override_amount !== null
            ? (string) $this->override_amount
            : ($this->salaryComponent->amount !== null ? (string) $this->salaryComponent->amount : null);
    }

    public function effectivePercentageValue(): ?string
    {
        return $this->override_percentage_value !== null
            ? (string) $this->override_percentage_value
            : ($this->salaryComponent->percentage_value !== null ? (string) $this->salaryComponent->percentage_value : null);
    }

    public function effectivePercentageBase(): ?PercentageBase
    {
        return $this->override_percentage_base ?? $this->salaryComponent->percentage_base;
    }
}