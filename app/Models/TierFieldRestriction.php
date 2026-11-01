<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tier field-level restriction rules.
 *
 * Defines which fields should be masked/hidden for a given tier
 * when accessing a specific resource (e.g. mask contact_number for free tier leads).
 */
class TierFieldRestriction extends Model
{
    protected $fillable = [
        'tier_id',
        'resource',
        'field_name',
        'restriction_type',
    ];

    // ── Relationships ──────────────────────────────────────────────

    /**
     * The tier this restriction belongs to.
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }
}
