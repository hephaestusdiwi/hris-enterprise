<?php

namespace App\Modules\Bpjs\Models;

use App\Modules\Bpjs\Enums\BpjsCostBearer;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBpjsParticipation extends Model
{
    protected $fillable = [
        'employee_id',
        'bpjs_health_number',
        'bpjs_health_family_count',
        'bpjs_health_start_date',
        'bpjs_health_cost_bearer',
        'bpjs_employment_number',
        'bpjs_registration_npp_number',
        'bpjs_employment_start_date',
        'jht_cost_bearer',
    ];

    protected function casts(): array
    {
        return [
            'bpjs_health_family_count' => 'integer',
            'bpjs_health_start_date' => 'date',
            'bpjs_health_cost_bearer' => BpjsCostBearer::class,
            'bpjs_employment_start_date' => 'date',
            'jht_cost_bearer' => BpjsCostBearer::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}