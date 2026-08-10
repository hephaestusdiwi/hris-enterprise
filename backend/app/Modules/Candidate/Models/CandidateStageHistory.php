<?php 

namespace App\Modules\Candidate\Models;

use App\Models\User;
use App\Modules\Candidate\Enums\CandidateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateStageHistory extends Model
{
    protected $fillable = [
        'candidate_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'notes',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => CandidateStatus::class,
            'to_status' => CandidateStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}