<?php
 
namespace App\Modules\Bpjs\Models;
 
use Illuminate\Database\Eloquent\Model;

class BpjsJkkRiskClassRate extends Model
{
    protected $fillable = [
        'risk_class',
        'effective_date',
        'is_active',
        'employer_rate_percentage',
    ];
 
    protected function casts(): array
    {
        return [
            'risk_class' => 'integer',
            'effective_date' => 'date',
            'is_active' => 'boolean',
            'employer_rate_percentage' => 'decimal:2',
        ];
    }
}