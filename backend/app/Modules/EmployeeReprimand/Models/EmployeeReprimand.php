<?php

namespace App\Modules\EmployeeReprimand\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeReprimand\Enums\EmployeeReprimandStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class EmployeeReprimand extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'verbal_warning', 'sp1', 'sp2', 'sp3', 'termination_notice',
    ];

    protected $fillable = [
        'employee_id',
        'reprimand_type',
        'title',
        'date',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
        'created_by_user_id',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $appends = ['attachment_url'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => EmployeeReprimandStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /**
     * Sama filosofi EmployeeDeduction::isEditable() -- record yang udah
     * di-void gak boleh diedit lagi (harus void baru kalau salah lagi,
     * bukan diubah diam-diam).
     */
    public function isEditable(): bool
    {
        return $this->status === EmployeeReprimandStatus::Active;
    }
}