<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Master lookup for granular API scopes (e.g. leads:read, tags:write).
 *
 * Linked to tiers via tier_scopes pivot and to API keys via api_key_scopes pivot.
 */
class ApiScope extends Model
{
    protected $fillable = [
        'name',
        'resource',
        'action',
        'description',
        'is_mutation',
    ];

    protected function casts(): array
    {
        return [
            'is_mutation' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    /**
     * Tiers that grant this scope.
     */
    public function tiers(): BelongsToMany
    {
        return $this->belongsToMany(Tier::class, 'tier_scopes');
    }

    /**
     * API keys that have this scope granted.
     */
    public function apiKeys(): BelongsToMany
    {
        return $this->belongsToMany(ApiKey::class, 'api_key_scopes');
    }
}
