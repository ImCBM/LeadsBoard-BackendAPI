<?php

namespace App\Http\Middleware;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Services\TierLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces granular resource-action scopes on API routes.
 *
 * Usage in routes:
 *   ->middleware('scope:leads:read')
 *   ->middleware('scope:leads:write')
 *   ->middleware('scope:leads:delete,leads:write')  // requires ANY of these
 *
 * Behavior:
 * - Sanctum UI sessions: Full access (or checks user role for admin).
 * - API Keys: Validates that the key has the required scope(s).
 * - Also checks tier-level scope restrictions.
 */
class RequireScope
{
    public function __construct(
        private TierLimiter $tierLimiter
    ) {}

    /**
     * @param  string  ...$scopes  One or more scope strings. The key must have AT LEAST ONE.
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        // If no scopes specified, pass through
        if (empty($scopes)) {
            return $next($request);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        // Sanctum authenticated users (no API key) get full access
        if (!$apiKey && $request->user()) {
            return $next($request);
        }

        // If no API key and no user, the auth middleware should have caught this
        if (!$apiKey) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Authentication required.',
            ], 401);
        }

        // Check key type restrictions (public keys cannot perform mutations)
        foreach ($scopes as $scope) {
            if ($apiKey->hasScope($scope)) {
                // Also check tier-level scope restriction
                $tier = $apiKey->tier ?? config('tiers.default');
                if ($this->tierLimiter->tierAllowsScope($tier, $scope)) {
                    return $next($request);
                }
            }
        }

        // None of the required scopes were satisfied
        return response()->json([
            'error' => 'insufficient_scope',
            'message' => 'Your API key does not have the required permissions for this action.',
            'required_scopes' => $scopes,
            'key_type' => $apiKey->key_type->value,
            'hint' => $apiKey->isPublic()
                ? 'Public keys (lb_pub_) are restricted to read-only operations. Use a secret key (lb_sec_) for write/delete actions.'
                : 'This key is missing the required scope(s). Update the key permissions or create a new key with the correct scopes.',
        ], 403);
    }
}
