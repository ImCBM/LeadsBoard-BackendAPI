<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\TierLimiter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified multi-auth middleware supporting both Sanctum tokens and API Keys.
 *
 * Authentication flow:
 * 1. Check for Sanctum bearer token (auth:sanctum) -> Authenticates User.
 * 2. Check for API Key (X-API-Key header or Authorization: Bearer lb_...) ->
 *    Looks up hashed key, validates, enforces IP whitelist & rate limits.
 * 3. If neither valid: returns 401.
 *
 * After authentication, attaches rate limit headers to the response.
 */
class AuthenticateApiRequest
{
    public function __construct(
        private TierLimiter $tierLimiter
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // ── Try Sanctum first ──────────────────────────────────────
        if ($this->attemptSanctumAuth($request)) {
            $user = $request->user();
            $rateLimit = $this->tierLimiter->resolveUserRateLimit($user);
            $rateLimitResult = $this->tierLimiter->checkRateLimit("user:{$user->id}", $rateLimit);

            if ($rateLimitResult['exceeded']) {
                return $this->rateLimitResponse($rateLimitResult);
            }

            $response = $next($request);
            return $this->addRateLimitHeaders($response, $rateLimitResult);
        }

        // ── Try API Key ────────────────────────────────────────────
        $rawKey = $this->extractApiKey($request);
        if ($rawKey) {
            $apiKey = $this->resolveApiKey($rawKey);

            if (!$apiKey) {
                return response()->json([
                    'error' => 'invalid_api_key',
                    'message' => 'Invalid, expired, or deactivated API key.',
                ], 401);
            }

            // IP whitelist check
            if (!$apiKey->isIpAllowed($request->ip())) {
                return response()->json([
                    'error' => 'ip_not_allowed',
                    'message' => 'This API key is not authorized for your IP address.',
                ], 403);
            }

            // Rate limiting
            $rateLimit = $this->tierLimiter->resolveRateLimit($apiKey);
            $rateLimitResult = $this->tierLimiter->checkRateLimit("api_key:{$apiKey->id}", $rateLimit);

            if ($rateLimitResult['exceeded']) {
                return $this->rateLimitResponse($rateLimitResult);
            }

            // Attach API key to request for downstream use
            $request->attributes->set('api_key', $apiKey);

            // If key belongs to a user, set user resolver for downstream compatibility
            if ($apiKey->user_id) {
                $request->setUserResolver(function () use ($apiKey) {
                    return $apiKey->user;
                });
            }

            // Record usage
            $apiKey->recordUsage();

            $response = $next($request);
            return $this->addRateLimitHeaders($response, $rateLimitResult);
        }

        // ── Try Webhook Secret ─────────────────────────────────────
        $webhookSecret = config('services.webhook.secret');
        if (!empty($webhookSecret)) {
            $token = $request->bearerToken() ?: $request->header('X-Webhook-Token');
            if ($token && hash_equals($webhookSecret, $token)) {
                $apiKey = new ApiKey([
                    'name' => 'System Webhook Secret',
                    'plain_text_prefix' => 'webhook_secret',
                    'key_type' => \App\Enums\ApiKeyType::Secret,
                    'scopes' => ['*'],
                    'tier' => 'enterprise',
                    'rate_limit_per_minute' => 0,
                    'is_active' => true,
                ]);
                $apiKey->id = 0;
                $request->attributes->set('api_key', $apiKey);

                return $next($request);
            }
        }

        // ── No valid auth ──────────────────────────────────────────
        $message = $request->is('api/v1/webhook*')
            ? 'Invalid or missing webhook token.'
            : 'Authentication required. Provide a Sanctum bearer token or API key via X-API-Key header.';

        return response()->json([
            'error' => 'unauthenticated',
            'message' => $message,
        ], 401);
    }

    /**
     * Try to authenticate via Sanctum.
     */
    private function attemptSanctumAuth(Request $request): bool
    {
        // If bearer starts with 'lb_', it's an API key, not Sanctum
        $bearer = $request->bearerToken();
        if ($bearer && str_starts_with($bearer, 'lb_')) {
            return false;
        }

        // Try Sanctum auth (works for bearer tokens, sessions, or actingAs in tests)
        if (Auth::guard('sanctum')->check()) {
            Auth::shouldUse('sanctum');
            return true;
        }

        return false;
    }

    /**
     * Extract API key from request (multiple methods supported).
     */
    private function extractApiKey(Request $request): ?string
    {
        // 1. X-API-Key header
        $header = $request->header('X-API-Key');
        if ($header) {
            return $header;
        }

        // 2. Authorization: Bearer lb_... (API key via bearer)
        $bearer = $request->bearerToken();
        if ($bearer && str_starts_with($bearer, 'lb_')) {
            return $bearer;
        }

        // 3. Query parameter (convenience for testing, not recommended for production)
        $query = $request->query('api_key');
        if ($query) {
            return $query;
        }

        return null;
    }

    /**
     * Look up and validate the API key.
     */
    private function resolveApiKey(string $rawKey): ?ApiKey
    {
        $hashedKey = hash('sha256', $rawKey);

        /** @var ApiKey|null $apiKey */
        $apiKey = Cache::remember("api_key:{$hashedKey}", 300, function () use ($hashedKey) {
            return ApiKey::where('key', $hashedKey)->first();
        });

        if (!$apiKey || !$apiKey->isValid()) {
            // Clear cache for invalid keys
            Cache::forget("api_key:{$hashedKey}");
            return null;
        }

        return $apiKey;
    }

    /**
     * Generate a 429 rate limit exceeded response.
     */
    private function rateLimitResponse(array $rateLimitResult): Response
    {
        return response()->json([
            'error' => 'rate_limit_exceeded',
            'message' => 'Rate limit exceeded for this API key.',
            'retry_after_seconds' => $rateLimitResult['retry_after'],
        ], 429)->withHeaders([
            'Retry-After' => $rateLimitResult['retry_after'],
            'X-RateLimit-Limit' => $rateLimitResult['limit'],
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Attach rate limit info headers to successful responses.
     */
    private function addRateLimitHeaders(Response $response, array $rateLimitResult): Response
    {
        if ($rateLimitResult['limit'] > 0) {
            $response->headers->set('X-RateLimit-Limit', (string) $rateLimitResult['limit']);
            $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitResult['remaining']);
        }

        return $response;
    }
}
