<?php

namespace App\Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model 
{
    /**
     * Model ini ada di namespace non-standar (App\Modules\...\Models),
     * jadi convention-based factory discovery Laravel (Database\Factories\Modules\...)
     * tidak nemu factory-nya. Override eksplisit ke lokasi factory yang sebenarnya.
     */
    protected static function newFactory()
    {
        return \Database\Factories\CompanyFactory::new();
    }

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'npwp',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
