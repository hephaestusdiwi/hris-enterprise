<?php
 
namespace App\Modules\Candidate\Models;
 
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateBlacklist extends Model
{
    protected $fillable = [
        'email',
        'full_name',
        'candidate_id',
        'reason',
        'blacklisted_by_employee_id',
        'blacklisted_at',
    ];

    protected function casts(): array
    {
        return [
            'blacklisted_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function blacklistedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'blacklisted_by_employee_id');
    }
}