<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Employee\Models\Employee;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_status',
        'invited_at',
        'activation_token_hash',
        'activation_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'activation_token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'invited_at' => 'datetime',
            'activation_token_expires_at' => 'datetime',
        ];
    }

    public function isPendingInvite(): bool
    {
        return $this->account_status === 'pending_invite';
    }

    public function isActivationTokenValid(string $plainToken): bool
    {
        if (! $this->activation_token_hash || ! $this->activation_token_expires_at) {
            return false;
        }

        if ($this->activation_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->activation_token_hash, hash('sha256', $plainToken));
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}