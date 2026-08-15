<?php

namespace App\Modules\NewJoiner\Models;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\NewJoiner\Enums\NewJoinerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewJoiner extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id', 'token', 'status', 'gender', 'birth_place', 'birth_date', 'marital_status',
        'address', 'emergency_contact_name', 'emergency_contact_phone', 'national_id_number',
        'tax_number', 'bank_name', 'bank_account_number', 'bank_account_holder_name', 'photo_path',
        'sent_by_user_id', 'sent_at', 'expires_at', 'submitted_at', 'ready_for_employee_at',
        'employee_id',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'status' => NewJoinerStatus::class,
            'birth_date' => 'date',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'ready_for_employee_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Employee\Models\Employee::class);
    }
}