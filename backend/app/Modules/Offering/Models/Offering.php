<?php

namespace App\Modules\Offering\Models;

use App\Models\User;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Offering\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offering extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id', 'proposed_start_date', 'proposed_salary', 'compensation_notes',
        'notes', 'status', 'created_by_user_id', 'sent_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferingStatus::class,
            'proposed_start_date' => 'date',
            'proposed_salary' => 'decimal:2',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}