<?php

namespace App\Modules\ApprovalFlow\Models;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_flow_id',
        'sequence',
        'name',
        'approver_type',
        'approver_employee_id',
        'approver_role_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'approver_type' => ApproverType::class,
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }
}