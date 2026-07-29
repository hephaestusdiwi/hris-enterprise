<?php

namespace App\Modules\SalaryStructure\Models;

use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'effective_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SalaryStructureDetail::class)->orderBy('display_order');
    }
}