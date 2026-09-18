<?php
 
namespace App\Modules\EmployeeExperience\Models;
 
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExperience extends Model
{
    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'internship', 'freelance'];

    protected $fillable = [
        'employee_id',
        'company_name',
        'position_title',
        'employment_type',
        'start_date',
        'end_date',
        'description',
        'reason_for_leaving',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}