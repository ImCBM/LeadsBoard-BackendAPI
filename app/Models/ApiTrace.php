<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trace record for API requests.
 *
 * Populated via terminable middleware (ApiTraceLogger) after the
 * response is sent, so it has zero latency impact on the caller.
 */
class ApiTrace extends Model
{
    /**
     * Disable default timestamps since we only use created_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'trace_id',
        'api_key_id',
        'user_id',
        'key_prefix',
        'method',
        'path',
        'route_name',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'query_params',
        'request_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'query_params' => 'array',
            'request_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    /**
     * The API key that made this request (if any).
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * The user who made this request (if authenticated via Sanctum).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Pruning ────────────────────────────────────────────────────

    /**
     * Delete traces older than the specified number of days.
     */
    public static function prune(int $days = 30): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}
