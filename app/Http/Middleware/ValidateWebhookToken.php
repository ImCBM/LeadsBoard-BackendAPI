<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the webhook token for n8n and similar integrations.
 * 
 * Accepts the token via:
 *   - Authorization: Bearer <token>
 *   - X-Webhook-Token: <token>
 * 
 * The expected token is stored in .env as WEBHOOK_SECRET.
 */
class ValidateWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.webhook.secret');

        if (empty($expectedToken)) {
            // If no webhook secret is configured, reject all webhook requests
            return response()->json([
                'message' => 'Webhook authentication is not configured on the server.',
            ], 503);
        }

        $providedToken = $this->extractToken($request);

        if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Invalid or missing webhook token.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Extract the token from the request headers.
     */
    private function extractToken(Request $request): ?string
    {
        // Check Authorization: Bearer <token>
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $bearerToken;
        }

        // Check X-Webhook-Token header
        $webhookToken = $request->header('X-Webhook-Token');
        if ($webhookToken) {
            return $webhookToken;
        }

        return null;
    }
}
