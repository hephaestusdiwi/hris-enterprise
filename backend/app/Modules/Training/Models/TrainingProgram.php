<?php
 
namespace App\Modules\Training\Models;
 
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Training\Enums\TrainingProgramStatus;
use App\Modules\Training\Enums\TrainingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgram extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'training_category_id',
        'title',
        'description',
        'type',
        'organizer',
        'pic_employee_id',
        'budget',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrainingType::class,
            'budget' => 'decimal:2',
            'status' => TrainingProgramStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingCategory::class, 'training_category_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(TrainingRecipient::class);
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(TrainingParticipant::class, TrainingSession::class);
    }
}