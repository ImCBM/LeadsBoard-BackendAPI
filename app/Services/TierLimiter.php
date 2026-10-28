<?php

namespace App\Services;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Resolves effective rate limits, quotas, and capabilities
 * based on the account tier system defined in config/tiers.php.
 *
 * Keys can override tier defaults via their own rate_limit_per_minute.
 */
class TierLimiter
{
    /**
     * Get the tier configuration for a given tier name.
     */
    public function getTierConfig(string $tier): array
    {
        return config("tiers.tiers.{$tier}", config('tiers.tiers.' . config('tiers.default', 'free')));
    }

    /**
     * Resolve the effective rate limit per minute for an API key.
     *
     * Priority:
     * 1. Key-level custom override (rate_limit_per_minute > 0)
     * 2. Tier default from config
     * 3. System fallback (120 req/min)
     */
    public function resolveRateLimit(ApiKey $apiKey): int
    {
        // Key-level custom override
        $keyLimit = $apiKey->getEffectiveRateLimit();
        if ($keyLimit !== null && $keyLimit > 0) {
            return $keyLimit;
        }

        // If key limit is 0 (unlimited), honor it
        if ($keyLimit === 0) {
            return 0;
        }

        // Tier default
        $tierConfig = $this->getTierConfig($apiKey->tier ?? config('tiers.default'));
        return $tierConfig['rate_limit_per_minute'] ?? 120;
    }

    /**
     * Resolve the effective rate limit for a Sanctum-authenticated user.
     */
    public function resolveUserRateLimit(User $user): int
    {
        $tierConfig = $this->getTierConfig($user->getTier());
        return $tierConfig['rate_limit_per_minute'] ?? 120;
    }

    /**
     * Check if the caller has exceeded their rate limit.
     *
     * @return array{exceeded: bool, limit: int, remaining: int, retry_after: int|null}
     */
    public function checkRateLimit(string $identifier, int $limit): array
    {
        // 0 = unlimited
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
    public function tierAllows(string $tier, string $capability): bool
    {
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
    public function tierAllowsScope(string $tier, string $scope): bool
    {
        $tierConfig = $this->getTierConfig($tier);
        $allowedScopes = $tierConfig['allowed_scopes'] ?? [];

        return ApiScope::scopesSatisfy($allowedScopes, $scope);
    }

    /**
     * Get the maximum number of API keys allowed for a tier.
     */
    public function getMaxKeys(string $tier): int
    {
        $tierConfig = $this->getTierConfig($tier);
        return $tierConfig['max_keys'] ?? 3;
    }
}
