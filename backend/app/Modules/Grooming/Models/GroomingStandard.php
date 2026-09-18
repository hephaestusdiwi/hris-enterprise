<?php

namespace App\Modules\Grooming\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Grooming\Enums\GroomingStandardStatus;
use App\Modules\Grooming\Enums\GroomingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroomingStandard extends Model
{
    protected $fillable = [
        'type',
        'name',
        'description',
        'version_number',
        'effective_date',
        'status',
        'created_by_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => GroomingType::class,
            'status' => GroomingStandardStatus::class,
            'effective_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GroomingStandardItem::class)->orderBy('sort_order');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }
}