<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User subscription records linking users to tiers.
 *
 * Adheres to 3NF by tracking tier memberships over time
 * without duplicating tier quotas or scope permissions.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tier_id',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    /**
     * User who holds the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tier plan assigned to this subscription.
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * Filter query to only active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }
}
