<?php
 
namespace App\Modules\Training\Models;
 
use App\Models\User;
use App\Modules\Training\Enums\TrainingRecipientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
/**
 * Recipient EKSTRA (di luar peserta) untuk reminder training. Peserta
 * (TrainingParticipant) otomatis jadi recipient tanpa baris di sini --
 * lihat komentar migration training_recipients.
 */
class TrainingRecipient extends Model
{
    protected $fillable = ['training_program_id', 'recipient_type', 'user_id', 'role'];

    protected function casts(): array
    {
        return ['recipient_type' => TrainingRecipientType::class];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}