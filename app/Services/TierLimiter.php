<?php

namespace App\Services;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Tier-based rate limiter, quota manager, and capability verifier.
 *
 * Implements:
 * - Dual-Bucket Rate Limiting (Account aggregate ceiling + Key budget isolation)
 * - Daily request quota tracking
 * - Tier capability checks (can_export, can_bulk_operations)
 */
class TierLimiter
{
    /**
     * Get the tier configuration for a given tier name (fallback for legacy configs).
     */
    public function getTierConfig(string $tier): array
    {
        return config("tiers.tiers.{$tier}", config('tiers.tiers.' . config('tiers.default', 'free'), []));
    }

    /**
     * Resolve the effective rate limit per minute for an API key.
     *
     * Priority:
     * 1. Key-level custom override (rate_limit_per_minute > 0)
     * 2. Key-level unlimited (0)
     * 3. Tier model rate_limit_per_minute
     * 4. System fallback (120 req/min)
     */
    public function resolveRateLimit(ApiKey $apiKey): int
    {
        $keyLimit = $apiKey->getEffectiveRateLimit();

        if ($keyLimit !== null && $keyLimit > 0) {
            return $keyLimit;
        }

        if ($keyLimit === 0) {
            return 0;
        }

        if ($apiKey->tier instanceof Tier) {
            return $apiKey->tier->rate_limit_per_minute;
        }

        $tierConfig = $this->getTierConfig($apiKey->tier ?? config('tiers.default', 'free'));
        return $tierConfig['rate_limit_per_minute'] ?? 120;
    }

    /**
     * Resolve the effective rate limit for a Sanctum-authenticated user.
     */
    public function resolveUserRateLimit(User $user): int
    {
        $tier = $user->getTier();

        if ($tier instanceof Tier) {
            return $tier->rate_limit_per_minute;
        }

        $tierConfig = $this->getTierConfig($tier ?? config('tiers.default', 'free'));
        return $tierConfig['rate_limit_per_minute'] ?? 120;
    }

    /**
     * Enforce Dual-Bucket Rate Limiting:
     * 1. Daily request quota (per user)
     * 2. Account-level aggregate bucket (per user per minute <= tier rate limit)
     * 3. Key-level budget bucket (per key per minute <= key rate limit)
     *
     * @return array{
     *     exceeded: bool,
     *     throttled_by?: string,
     *     limit: int,
     *     remaining: int,
     *     reset: int,
     *     retry_after?: int|null
     * }
     */
    public function checkDualBucketLimit(ApiKey $apiKey): array
    {
        $tier = $apiKey->tier;
        $now = now();
        $minuteWindow = (int) floor($now->timestamp / 60);
        $resetTime = ($minuteWindow + 1) * 60;
        $secondsUntilReset = max(1, $resetTime - $now->timestamp);

        $accountRateLimit = $tier?->rate_limit_per_minute ?? 60;
        $dailyQuotaLimit = $tier?->daily_request_limit ?? 5000;
        $keyRateLimit = $apiKey->getEffectiveRateLimit() ?? $accountRateLimit;

        $userId = $apiKey->user_id;

        // ── 1. Daily Quota Check ─────────────────────────────────────
        if ($userId && $dailyQuotaLimit > 0) {
            $today = $now->format('Y-m-d');
            $dailyKey = "api_quota:daily:user:{$userId}:{$today}";
            $dailyCount = (int) Cache::get($dailyKey, 0);

            if ($dailyCount >= $dailyQuotaLimit) {
                return [
                    'exceeded' => true,
                    'throttled_by' => 'daily_quota',
                    'limit' => $dailyQuotaLimit,
                    'remaining' => 0,
                    'reset' => $now->copy()->endOfDay()->timestamp,
                    'retry_after' => 86400 - ($now->timestamp % 86400),
                ];
            }
        }

        // ── 2. Account-Level Bucket (Aggregate quota per minute) ─────
        if ($userId && $accountRateLimit > 0) {
            $accountKey = "api_rate:account:user:{$userId}:{$minuteWindow}";
            $accountCount = (int) Cache::get($accountKey, 0);

            if ($accountCount >= $accountRateLimit) {
                return [
                    'exceeded' => true,
                    'throttled_by' => 'account',
                    'limit' => $accountRateLimit,
                    'remaining' => 0,
                    'reset' => $resetTime,
                    'retry_after' => $secondsUntilReset,
                ];
            }
        }

        // ── 3. Key-Level Bucket (Per-key budget) ─────────────────────
        if ($keyRateLimit > 0) {
            $keyKey = "api_rate:key:{$apiKey->id}:{$minuteWindow}";
            $keyCount = (int) Cache::get($keyKey, 0);

            if ($keyCount >= $keyRateLimit) {
                return [
                    'exceeded' => true,
                    'throttled_by' => 'key',
                    'limit' => $keyRateLimit,
                    'remaining' => 0,
                    'reset' => $resetTime,
                    'retry_after' => $secondsUntilReset,
                ];
            }
        }

        // ── Record / Increment Buckets ───────────────────────────────
        if ($userId && $dailyQuotaLimit > 0) {
            $dailyKey = "api_quota:daily:user:{$userId}:" . $now->format('Y-m-d');
            if (Cache::has($dailyKey)) {
                Cache::increment($dailyKey);
            } else {
                Cache::put($dailyKey, 1, 86400 * 2);
            }
        }

        if ($userId && $accountRateLimit > 0) {
            $accountKey = "api_rate:account:user:{$userId}:{$minuteWindow}";
            if (Cache::has($accountKey)) {
                Cache::increment($accountKey);
            } else {
                Cache::put($accountKey, 1, 120);
            }
        }

        $newKeyCount = 1;
        if ($keyRateLimit > 0) {
            $keyKey = "api_rate:key:{$apiKey->id}:{$minuteWindow}";
            if (Cache::has($keyKey)) {
                $newKeyCount = Cache::increment($keyKey);
            } else {
                Cache::put($keyKey, 1, 120);
                $newKeyCount = 1;
            }
        }

        $effectiveLimit = $keyRateLimit > 0 ? $keyRateLimit : $accountRateLimit;
        $remaining = max(0, $effectiveLimit - $newKeyCount);

        return [
            'exceeded' => false,
            'limit' => $effectiveLimit,
            'remaining' => $remaining,
            'reset' => $resetTime,
            'retry_after' => null,
        ];
    }

    /**
     * Check if the caller has exceeded their single identifier rate limit (legacy Sanctum).
     *
     * @return array{exceeded: bool, limit: int, remaining: int, retry_after: int|null}
     */
    public function checkRateLimit(string $identifier, int $limit): array
    {
        if ($limit === 0) {
            return [
                'exceeded' => false,
                'limit' => 0,
                'remaining' => PHP_INT_MAX,
                'retry_after' => null,
            ];
        }

        $rateLimitKey = "api_rate:{$identifier}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            return [
                'exceeded' => true,
                'limit' => $limit,
                'remaining' => 0,
                'retry_after' => RateLimiter::availableIn($rateLimitKey),
            ];
        }

        RateLimiter::hit($rateLimitKey, 60);

        return [
            'exceeded' => false,
            'limit' => $limit,
            'remaining' => max(0, $limit - RateLimiter::attempts($rateLimitKey)),
            'retry_after' => null,
        ];
    }

    /**
     * Check if a specific capability is allowed under the given tier.
     */
    public function tierAllows(string|Tier $tier, string $capability): bool
    {
        if ($tier instanceof Tier) {
            return match ($capability) {
                'export' => $tier->can_export,
                'bulk_operations' => $tier->can_bulk_operations,
                default => false,
            };
        }

        $tierModel = Tier::where('slug', $tier)->first();
        if ($tierModel) {
            return match ($capability) {
                'export' => $tierModel->can_export,
                'bulk_operations' => $tierModel->can_bulk_operations,
                default => false,
            };
        }

        $tierConfig = $this->getTierConfig($tier);
        return match ($capability) {
            'export' => $tierConfig['can_export'] ?? false,
            'bulk_operations' => $tierConfig['can_bulk_operations'] ?? false,
            default => false,
        };
    }

    /**
     * Check if the given scope is allowed under the tier's allowed scopes.
     */
    public function tierAllowsScope(string|Tier $tier, string $scope): bool
    {
        if ($tier instanceof Tier) {
            return $tier->hasScope($scope);
        }

        $tierModel = Tier::where('slug', $tier)->first();
        if ($tierModel) {
            return $tierModel->hasScope($scope);
        }

        $tierConfig = $this->getTierConfig($tier);
        $allowedScopes = $tierConfig['allowed_scopes'] ?? [];
        return ApiScope::scopesSatisfy($allowedScopes, $scope);
    }

    /**
     * Get the maximum number of API keys allowed for a tier.
     */
    public function getMaxKeys(string|Tier $tier): int
    {
        if ($tier instanceof Tier) {
            return $tier->max_keys;
        }

        $tierModel = Tier::where('slug', $tier)->first();
        if ($tierModel) {
            return $tierModel->max_keys;
        }

        $tierConfig = $this->getTierConfig($tier);
        return $tierConfig['max_keys'] ?? 3;
    }
}
