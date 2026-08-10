<?php

namespace App\Modules\EmploymentType\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentType extends Model
{
    /**
     * Model ini ada di namespace non-standar (App\Modules\...\Models),
     * jadi convention-based factory discovery Laravel (Database\Factories\Modules\...)
     * tidak nemu factory-nya. Override eksplisit ke lokasi factory yang sebenarnya.
     */
    protected static function newFactory()
    {
        return \Database\Factories\EmploymentTypeFactory::new();
    }

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}