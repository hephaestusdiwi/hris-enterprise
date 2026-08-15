<?php

namespace App\Modules\ContractProbationSetting\Models;

use Illuminate\Database\Eloquent\Model;

class ContractProbationSetting extends Model
{
    protected $fillable = [
        'contract_reminder_days',
        'probation_reminder_days',
        'email_reminder_enabled',
        'manager_reminder_enabled',
    ];

    protected function casts(): array
    {
        return [
            'contract_reminder_days' => 'integer',
            'probation_reminder_days' => 'integer',
            'email_reminder_enabled' => 'boolean',
            'manager_reminder_enabled' => 'boolean',
        ];
    }

    /**
     * Satu-satunya row (global, singleton). Kalau belum pernah di-seed
     * (mis. environment lama), fallback ke config sebagai default —
     * jangan sampai null/crash.
     */
    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'contract_reminder_days' => config('contract_probation.contract_reminder_days', 30),
            'probation_reminder_days' => config('contract_probation.probation_reminder_days', 30),
            'email_reminder_enabled' => true,
            'manager_reminder_enabled' => true,
        ]);
    }
}
