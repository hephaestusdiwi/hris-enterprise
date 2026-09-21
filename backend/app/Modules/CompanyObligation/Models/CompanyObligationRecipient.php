<?php

namespace App\Modules\CompanyObligation\Models;

use App\Models\User;
use App\Modules\CompanyObligation\Enums\CompanyObligationRecipientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = 1 target reminder untuk sebuah CompanyObligation.
 *
 * - recipient_type=User: notify `user` (user_id wajib diisi).
 * - recipient_type=Pic: notify PIC obligation ybs (Employee->user dari
 *   CompanyObligation::pic()) -- TIDAK butuh user_id/role di baris ini,
 *   selalu ikut PIC obligation saat ini (kalau PIC berubah, otomatis ikut
 *   berubah tanpa perlu update baris recipient).
 * - recipient_type=Role: notify SEMUA user dengan role Spatie `role`,
 *   di-scope ke company yang sama dengan obligation (lihat
 *   CompanyObligationReminderService::recipientsFor()).
 */
class CompanyObligationRecipient extends Model
{
    protected $fillable = [
        'company_obligation_id',
        'recipient_type',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'recipient_type' => CompanyObligationRecipientType::class,
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(CompanyObligation::class, 'company_obligation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}