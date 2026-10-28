<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiScope;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use App\Services\TierLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for developers to manage their own API keys.
 *
 * Endpoints:
 *   GET    /api/v1/developer/api-keys       — List owned keys
 *   POST   /api/v1/developer/api-keys       — Create new key pair
 *   GET    /api/v1/developer/api-keys/{id}  — Get key details
 *   PATCH  /api/v1/developer/api-keys/{id}  — Update key settings
 *   DELETE /api/v1/developer/api-keys/{id}  — Revoke key
 *   POST   /api/v1/developer/api-keys/{id}/rotate — Rotate key value
 */
class DeveloperApiKeyController extends Controller
{
    public function __construct(
        private ApiKeyService $apiKeyService,
        private TierLimiter $tierLimiter
    ) {}

    /**
     * List all API keys owned by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $keys = ApiKey::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ApiKey $key) => $this->formatKey($key));

        return response()->json([
            'data' => $keys,
            'meta' => [
                'total' => $keys->count(),
                'max_keys' => $this->tierLimiter->getMaxKeys($user->getTier()),
                'tier' => $user->getTier(),
            ],
        ]);
    }

    /**
     * Create a new API key.
     *
     * Returns the plaintext key ONCE — it cannot be retrieved again.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key_type' => 'required|in:public,secret',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:50',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'metadata' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        // Check key count limit
        $currentCount = ApiKey::where('user_id', $user->id)->count();
        $maxKeys = $this->tierLimiter->getMaxKeys($user->getTier());
        if ($currentCount >= $maxKeys) {
            return response()->json([
                'error' => 'key_limit_reached',
                'message' => "Your tier ({$user->getTier()}) allows a maximum of {$maxKeys} API keys. You currently have {$currentCount}.",
            ], 422);
        }

        // Validate scopes
        if (!empty($validated['scopes'])) {
            $invalidScopes = $this->apiKeyService->validateScopes($validated['scopes']);
            if (!empty($invalidScopes)) {
                return response()->json([
                    'error' => 'invalid_scopes',
                    'message' => 'One or more scopes are invalid.',
                    'invalid_scopes' => $invalidScopes,
                    'valid_scopes' => ApiScope::values(),
                    'valid_presets' => ['read-only', 'write-only', 'full'],
                ], 422);
            }
        }

        $result = $this->apiKeyService->createKey($validated, $user);

        return response()->json([
            'message' => 'API key created successfully.',
            'data' => $this->formatKey($result['api_key']),
            'plain_text_key' => $result['plain_text_key'],
            'warning' => 'Store this key securely. It will not be shown again.',
        ], 201);
    }

    /**
     * Get details for a specific owned key.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatKey($apiKey, detailed: true),
        ]);
    }

    /**
     * Update key settings (name, scopes, rate limit, IP whitelist, active status).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|max:50',
            'rate_limit_per_minute' => 'sometimes|nullable|integer|min:0',
            'allowed_ips' => 'sometimes|nullable|array',
            'allowed_ips.*' => 'ip',
            'metadata' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        // Validate scopes if being updated
        if (isset($validated['scopes'])) {
            $invalidScopes = $this->apiKeyService->validateScopes($validated['scopes']);
            if (!empty($invalidScopes)) {
                return response()->json([
                    'error' => 'invalid_scopes',
                    'message' => 'One or more scopes are invalid.',
                    'invalid_scopes' => $invalidScopes,
                ], 422);
            }

            // Resolve scopes (expand presets, filter by key type)
            $validated['scopes'] = $this->apiKeyService->resolveScopes(
                $validated['scopes'],
                $apiKey->key_type
            );
        }

        $apiKey->update($validated);

        // Clear cached key
        \Illuminate\Support\Facades\Cache::forget("api_key:{$apiKey->key}");

        return response()->json([
            'message' => 'API key updated successfully.',
            'data' => $this->formatKey($apiKey->refresh()),
        ]);
    }

    /**
     * Revoke and delete an API key.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Clear cached key
        \Illuminate\Support\Facades\Cache::forget("api_key:{$apiKey->key}");

        $apiKey->delete();

        return response()->json([
            'message' => 'API key revoked and deleted successfully.',
        ]);
    }

    /**
     * Rotate a key: generate a new key value while preserving all settings.
     */
    public function rotate(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Clear old cached key
        \Illuminate\Support\Facades\Cache::forget("api_key:{$apiKey->key}");

        $result = $this->apiKeyService->rotateKey($apiKey);

        return response()->json([
            'message' => 'API key rotated successfully. The old key is now invalid.',
            'data' => $this->formatKey($result['api_key']),
            'plain_text_key' => $result['plain_text_key'],
            'warning' => 'Store this new key securely. It will not be shown again.',
        ]);
    }

    /**
     * Format an API key for API response (never expose the hashed key).
     */
    private function formatKey(ApiKey $key, bool $detailed = false): array
    {
        $data = [
            'id' => $key->id,
            'name' => $key->name,
            'key_type' => $key->key_type->value,
            'prefix' => $key->plain_text_prefix,
            'scopes' => $key->getEffectiveScopes(),
            'tier' => $key->tier,
            'rate_limit_per_minute' => $key->rate_limit_per_minute,
            'is_active' => $key->is_active,
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'expires_at' => $key->expires_at?->toIso8601String(),
            'created_at' => $key->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['allowed_ips'] = $key->allowed_ips;
            $data['metadata'] = $key->metadata;
            $data['usage_count'] = $key->traces()->count();
        }

        return $data;
    }
}
