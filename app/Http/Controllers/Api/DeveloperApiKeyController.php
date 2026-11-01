<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiKeyType;
use App\Exceptions\ApiKeyQuotaExceededException;
use App\Exceptions\ScopeUnauthorizedException;
use App\Exceptions\TierUnauthorizedException;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Tier;
use App\Services\ApiKeyService;
use App\Services\TierLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Customer Portal REST API for developers to manage their own API keys.
 *
 * Locked strictly to the customer's subscription tier.
 */
class DeveloperApiKeyController extends Controller
{
    public function __construct(
        private ApiKeyService $apiKeyService,
        private TierLimiter $tierLimiter
    ) {}

    /**
     * List all API keys owned by the authenticated user with tier metadata.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tier = $user->getTier();

        $keys = ApiKey::where('user_id', $user->id)
            ->with(['scopes', 'tier'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ApiKey $key) => $this->formatKey($key));

        return response()->json([
            'data' => $keys,
            'meta' => [
                'total' => $keys->count(),
                'max_keys' => $tier?->max_keys ?? 3,
                'tier' => $tier?->slug ?? 'free',
                'tier_name' => $tier?->name ?? 'Free Client',
                'rate_limit_per_minute' => $tier?->rate_limit_per_minute ?? 60,
                'allowed_scopes' => $tier?->scopes()->pluck('name')->all() ?? [],
                'all_scopes' => ApiScope::where('name', '!=', '*')->pluck('name')->all(),
            ],
        ]);
    }

    /**
     * Create a new API key within user's tier ceiling.
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
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            $result = $this->apiKeyService->createKey($validated, $user);

            return response()->json([
                'message' => 'API key created successfully.',
                'data' => $this->formatKey($result['api_key']),
                'plain_text_key' => $result['plain_text_key'],
                'warning' => 'Store this key securely. It will not be shown again.',
            ], 201);
        } catch (ScopeUnauthorizedException | TierUnauthorizedException $e) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ApiKeyQuotaExceededException $e) {
            return response()->json([
                'error' => 'key_limit_reached',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get details for a specific owned key.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', $request->user()->id)
            ->with(['scopes', 'tier'])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatKey($apiKey, detailed: true),
        ]);
    }

    /**
     * Update key settings.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $apiKey = ApiKey::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|max:50',
            'rate_limit_per_minute' => 'sometimes|nullable|integer|min:0',
            'allowed_ips' => 'sometimes|nullable|array',
            'allowed_ips.*' => 'ip',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['scopes'])) {
            $tier = $user->getTier();
            if (!$user->isInternalDeveloper()) {
                $allowedScopes = $tier?->scopes()->pluck('name')->all() ?? [];
                foreach ($validated['scopes'] as $sc) {
                    if (!in_array($sc, $allowedScopes)) {
                        return response()->json([
                            'error' => 'unauthorized_scope',
                            'message' => "Scope '{$sc}' is not permitted by your tier.",
                        ], 403);
                    }
                }
            }
            $scopeIds = ApiScope::whereIn('name', $validated['scopes'])->pluck('id')->all();
            $apiKey->scopes()->sync($scopeIds);
            unset($validated['scopes']);
        }

        $apiKey->update($validated);

        return response()->json([
            'message' => 'API key updated successfully.',
            'data' => $this->formatKey($apiKey->fresh(['scopes', 'tier'])),
        ]);
    }

    /**
     * Revoke (delete) an API key.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);

        Cache::forget("api_key:{$apiKey->key}");
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
        $apiKey = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);

        Cache::forget("api_key:{$apiKey->key}");

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
            'key_type' => $key->key_type instanceof ApiKeyType ? $key->key_type->value : $key->key_type,
            'prefix' => $key->plain_text_prefix,
            'scopes' => $key->scopes->pluck('name')->all(),
            'tier' => $key->tier?->slug ?? 'free',
            'tier_name' => $key->tier?->name,
            'rate_limit_per_minute' => $key->rate_limit_per_minute,
            'is_active' => $key->is_active,
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'expires_at' => $key->expires_at?->toIso8601String(),
            'created_at' => $key->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['allowed_ips'] = $key->allowed_ips;
            $data['usage_count'] = $key->traces()->count();
        }

        return $data;
    }
}
