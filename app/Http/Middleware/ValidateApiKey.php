<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates API keys and enforces per-key rate limits.
 *
 * Accepts the key via:
 *   - Authorization: Bearer <key>
 *   - X-API-Key: <key>
 *   - ?api_key=<key> query parameter
 *
 * Each key can have its own rate_limit_per_minute:
 *   - null  → use system default (from config)
 *   - 0     → unlimited (no rate limiting)
 *   - N > 0 → N requests per minute
 */
class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $this->extractKey($request);

        if (!$rawKey) {
            return response()->json([
                'message' => 'API key is required. Provide via Authorization: Bearer <key>, X-API-Key header, or api_key query parameter.',
            ], 401);
        }

        // Look up the key (hashed match)
        $hashedKey = hash('sha256', $rawKey);
        $apiKey = Cache::remember("api_key:{$hashedKey}", 300, function () use ($hashedKey) {
            return ApiKey::where('key', $hashedKey)->first();
        });

        if (!$apiKey || !$apiKey->isValid()) {
            return response()->json([
                'message' => 'Invalid, expired, or deactivated API key.',
            ], 401);
        }

        // Enforce per-key rate limit
        $rateLimit = $apiKey->getEffectiveRateLimit();
        if ($rateLimit === null) {
            $rateLimit = config('services.api.default_rate_limit', 120);
        }

        // rateLimit of 0 means unlimited
        if ($rateLimit > 0) {
            $rateLimitKey = "api_key_rate:{$apiKey->id}";

            if (RateLimiter::tooManyAttempts($rateLimitKey, $rateLimit)) {
                $retryAfter = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'message' => 'Rate limit exceeded for this API key.',
                    'retry_after_seconds' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $rateLimit,
                    'X-RateLimit-Remaining' => 0,
                ]);
            }

            RateLimiter::hit($rateLimitKey, 60);
        }

        // Record usage (fire-and-forget, don't slow down the request)
        $apiKey->recordUsage();

        // Attach the API key to the request for downstream use
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    /**
     * Extract the API key from the request.
     */
    private function extractKey(Request $request): ?string
    {
        // 1. Authorization: Bearer <key>
        $bearer = $request->bearerToken();
        if ($bearer) {
            return $bearer;
        }

        // 2. X-API-Key header
        $header = $request->header('X-API-Key');
        if ($header) {
            return $header;
        }

        // 3. Query parameter
        $query = $request->query('api_key');
        if ($query) {
            return $query;
        }

        return null;
    }
}
