<?php

namespace App\Modules\Pph21\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Pph21\Enums\TaxMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTaxProfile extends Model
{
    protected $fillable = ['employee_id', 'tax_id_number', 'has_tax_id', 'tax_method'];

    protected function casts(): array
    {
        return [
            'has_tax_id' => 'boolean',
            'tax_method' => TaxMethod::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}