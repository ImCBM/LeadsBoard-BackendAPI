<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * Check if the user has admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user is an internal developer or admin.
     */
    public function isInternalDeveloper(): bool
    {
        return in_array($this->role, ['admin', 'developer']);
    }

    /**
     * API keys owned by this user.
     */
    public function apiKeys(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ApiKey::class);
    }

    /**
     * Subscriptions for this user.
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    /**
     * Active subscription for this user.
     */
    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Subscription::class)
            ->where('status', 'active');
    }

    /**
     * Get the user's effective Tier model.
     * Looks up active subscription's tier, or falls back to the default 'free' tier.
     */
    public function getTier(): ?Tier
    {
        $activeSub = $this->subscriptions()
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($activeSub && $activeSub->tier) {
            return $activeSub->tier;
        }

        if ($this->isInternalDeveloper()) {
            return Tier::where('slug', 'developer')->first() ?? Tier::where('slug', 'enterprise')->first();
        }

        return Tier::where('slug', 'free')->first();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }
}
