<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master lookup for account tiers (free, pro, enterprise).
 *
 * Defines rate limits, quotas, and capability flags.
 * Scopes are linked via the tier_scopes pivot table.
 * Field restrictions are linked via tier_field_restrictions.
 */
class Tier extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'rate_limit_per_minute',
        'daily_request_limit',
        'max_keys',
        'can_export',
        'can_bulk_operations',
    ];

    protected function casts(): array
    {
        return [
            'rate_limit_per_minute' => 'integer',
            'daily_request_limit' => 'integer',
            'max_keys' => 'integer',
            'can_export' => 'boolean',
            'can_bulk_operations' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    /**
     * Scopes granted to this tier via pivot.
     */
    public function scopes(): BelongsToMany
    {
        return $this->belongsToMany(ApiScope::class, 'tier_scopes');
    }

    /**
     * Field-level restrictions for this tier.
     */
    public function fieldRestrictions(): HasMany
    {
        return $this->hasMany(TierFieldRestriction::class);
    }

    /**
     * Subscriptions on this tier.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * API keys assigned to this tier.
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Check if this tier grants a specific scope.
     */
    public function hasScope(string $scopeName): bool
    {
        return $this->scopes()->where('name', $scopeName)->exists();
    }

    /**
     * Check if a specific field is restricted for this tier.
     */
    public function isFieldRestricted(string $resource, string $fieldName): bool
    {
        return $this->fieldRestrictions()
            ->where('resource', $resource)
            ->where('field_name', $fieldName)
            ->exists();
    }
}
