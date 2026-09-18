<?php

namespace App\Modules\EmployeeAsset\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAsset extends Model
{
    public const TYPES = [
        'laptop', 'mobile_phone', 'id_card', 'access_card', 'vehicle', 'other',
    ];

    public const CONDITIONS = ['new', 'good', 'fair', 'damaged'];

    protected $fillable = [
        'employee_id',
        'asset_type',
        'asset_name',
        'serial_number',
        'condition',
        'assigned_date',
        'returned_date',
        'assigned_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_date' => 'date',
            'returned_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}