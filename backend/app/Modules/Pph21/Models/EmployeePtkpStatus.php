<?php

namespace App\Modules\Pph21\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Pph21\Enums\PtkpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePtkpStatus extends Model
{
    protected $fillable = ['employee_id', 'ptkp_status', 'tax_year', 'effective_date'];

    protected function casts(): array
    {
        return [
            'ptkp_status' => PtkpStatus::class,
            'tax_year' => 'integer',
            'effective_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}