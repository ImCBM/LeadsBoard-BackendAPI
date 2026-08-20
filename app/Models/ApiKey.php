<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key',
        'plain_text_prefix',
        'rate_limit_per_minute',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Check if this key is currently valid (active + not expired).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get the effective rate limit for this key.
     * null = use system default, 0 = unlimited.
     */
    public function getEffectiveRateLimit(): ?int
    {
        return $this->rate_limit_per_minute;
    }

    /**
     * Record that this key was just used.
     */
    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
