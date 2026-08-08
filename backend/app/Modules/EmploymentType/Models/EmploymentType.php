<?php

namespace App\Modules\EmploymentType\Models;

use Database\Factories\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentType extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): EmploymentTypeFactory
    {
        return EmploymentTypeFactory::new();
    }

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