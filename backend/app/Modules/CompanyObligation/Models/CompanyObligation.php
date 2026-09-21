<?php

namespace App\Modules\CompanyObligation\Models;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\CompanyObligation\Enums\CompanyObligationStatus;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Module generic buat kebutuhan kewajiban perusahaan yang punya due date +
 * reminder: Sewa Ruko, MOU Legal, Jatuh Tempo Piutang -- dibedakan lewat
 * `type` (lihat TYPES), BUKAN 3 module terpisah. Kalau nanti ada kebutuhan
 * ke-4/5 yang polanya sama (due date + PIC + reminder), cukup tambah 1
 * entry di TYPES, tidak perlu tabel/module baru.
 */
class CompanyObligation extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_RENT = 'rent';

    public const TYPE_LEGAL_MOU = 'legal_mou';

    public const TYPE_RECEIVABLE_DUE = 'receivable_due';

    public const TYPES = [
        self::TYPE_RENT => 'Sewa Ruko',
        self::TYPE_LEGAL_MOU => 'MOU Legal',
        self::TYPE_RECEIVABLE_DUE => 'Jatuh Tempo Piutang',
    ];

    protected $fillable = [
        'company_id',
        'type',
        'title',
        'description',
        'pic_employee_id',
        'due_date',
        'amount',
        'status',
        'created_by_user_id',
    ];

    // remaining_days dipakai langsung oleh frontend (badge "H-7", dsb) --
    // harus di-append supaya ikut ke JSON tanpa frontend perlu hitung ulang.
    protected $appends = ['remaining_days'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'status' => CompanyObligationStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CompanyObligationRecipient::class);
    }

    /**
     * Sisa hari sampai due_date, dihitung dari hari ini (0 = jatuh tempo
     * hari ini, negatif = sudah lewat). Dipakai frontend & tidak disimpan
     * di kolom -- selalu dihitung ulang biar tidak basi.
     *
     * Sengaja pakai diffInDays() absolute (tanpa argumen $absolute=false)
     * lalu beri tanda manual sendiri -- signature signed diffInDays()
     * gampang salah arah antar versi Carbon, absolute+manual sign jauh
     * lebih eksplisit dan aman (lihat juga ContractProbationService yang
     * pakai pola sama, walau di sana selalu non-negatif karena sudah
     * difilter "upcoming" duluan).
     */
    public function getRemainingDaysAttribute(): int
    {
        $today = now()->startOfDay();
        $due = $this->due_date->copy()->startOfDay();
        $days = (int) $today->diffInDays($due);

        return $due->greaterThanOrEqualTo($today) ? $days : -$days;
    }

    public function isActive(): bool
    {
        return $this->status === CompanyObligationStatus::Active;
    }
}