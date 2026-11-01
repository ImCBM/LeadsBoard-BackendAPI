<?php

namespace App\Services;

use App\Enums\ApiKeyType;
use App\Exceptions\ApiKeyQuotaExceededException;
use App\Exceptions\ScopeUnauthorizedException;
use App\Exceptions\TierUnauthorizedException;
use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service for API key lifecycle management under the normalized 3NF architecture.
 *
 * Responsibilities (SRP):
 * - Validate client entitlements against their subscription tier ceiling.
 * - Enforce key-level budgeting (rate limit <= tier rate limit).
 * - Enforce maximum active keys quota.
 * - Generate cryptographic key pairs and persist hashes with tier_id and pivot scopes.
 */
class ApiKeyService
{
    /**
     * Generate a new API key pair for a user or standalone system use.
     *
     * @param  array{
     *     name: string,
     *     key_type?: string,
     *     tier_id?: int|null,
     *     scopes?: array<string>,
     *     rate_limit_per_minute?: int|null,
     *     allowed_ips?: array<string>|null,
     *     expires_at?: string|null,
     * }  $data
     * @param  User|null  $user
     * @return array{api_key: ApiKey, plain_text_key: string}
     *
     * @throws TierUnauthorizedException
     * @throws ScopeUnauthorizedException
     * @throws ApiKeyQuotaExceededException
     * @throws ValidationException
     */
    public function createKey(array $data, ?User $user = null): array
    {
        $isInternal = $user?->isInternalDeveloper() ?? true;

        // ── 1. Determine and Validate Tier ───────────────────────────
        $tier = $this->resolveAndValidateTier($data, $user, $isInternal);

        // ── 2. Check Key Quota ───────────────────────────────────────
        if ($user && !$isInternal) {
            $activeKeyCount = $user->apiKeys()->where('is_active', true)->count();
            if ($activeKeyCount >= $tier->max_keys) {
                throw new ApiKeyQuotaExceededException(
                    "You have reached the maximum allowed keys ({$tier->max_keys}) for the {$tier->name} tier."
                );
            }
        }

        // ── 3. Validate Key-level Budget Limit ───────────────────────
        $rateLimitPerMinute = $data['rate_limit_per_minute'] ?? null;
        if ($rateLimitPerMinute !== null && $rateLimitPerMinute > 0 && !$isInternal) {
            if ($rateLimitPerMinute > $tier->rate_limit_per_minute) {
                throw ValidationException::withMessages([
                    'rate_limit_per_minute' => "The key budget ({$rateLimitPerMinute} req/min) cannot exceed your tier maximum ({$tier->rate_limit_per_minute} req/min).",
                ]);
            }
        }

        // ── 4. Resolve and Validate Scopes ───────────────────────────
        $keyType = isset($data['key_type']) ? ApiKeyType::from($data['key_type']) : ApiKeyType::Secret;
        $scopeIds = $this->resolveAndValidateScopes($data['scopes'] ?? [], $tier, $keyType, $isInternal);

        // ── 5. Generate Cryptographic Key ────────────────────────────
        $plainTextKey = $this->generatePlainTextKey($keyType);
        $hashedKey = hash('sha256', $plainTextKey);

        // ── 6. Persist Normalized ApiKey Record ───────────────────────
        $apiKey = ApiKey::create([
            'user_id' => $user?->id,
            'tier_id' => $tier->id,
            'name' => $data['name'],
            'key_type' => $keyType,
            'key' => $hashedKey,
            'plain_text_prefix' => substr($plainTextKey, 0, 12),
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'is_active' => true,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        // ── 7. Attach Scopes via Pivot ───────────────────────────────
        if (!empty($scopeIds)) {
            $apiKey->scopes()->attach($scopeIds);
        }

        return [
            'api_key' => $apiKey,
            'plain_text_key' => $plainTextKey,
        ];
    }

    /**
     * Resolve the tier to assign, validating client access.
     */
    private function resolveAndValidateTier(array $data, ?User $user, bool $isInternal): Tier
    {
        if ($isInternal) {
            if (isset($data['tier_id'])) {
                return Tier::findOrFail($data['tier_id']);
            }
            return $user?->getTier() ?? Tier::where('slug', 'developer')->first() ?? Tier::where('slug', 'enterprise')->firstOrFail();
        }

        // For clients, tier is locked to their active subscription tier
        $userTier = $user->getTier();

        if (isset($data['tier_id']) && (int) $data['tier_id'] !== (int) $userTier->id) {
            throw new TierUnauthorizedException(
                "You cannot create an API key for a different tier than your subscription ({$userTier->name})."
            );
        }

        return $userTier;
    }

    /**
     * Resolve scope IDs and validate that clients do not request ungranted scopes.
     *
     * @return array<int>
     */
    private function resolveAndValidateScopes(array $requestedScopes, Tier $tier, ApiKeyType $keyType, bool $isInternal): array
    {
        if (empty($requestedScopes)) {
            // Default to tier's allowed scopes for client, or wildcard for admin
            if ($isInternal) {
                $wildcardScope = ApiScope::firstOrCreate(
                    ['name' => '*'],
                    ['resource' => '*', 'action' => '*', 'description' => 'Full access wildcard']
                );
                return [$wildcardScope->id];
            }

            return $tier->scopes()->pluck('api_scopes.id')->all();
        }

        // Wildcard requested
        if (in_array('*', $requestedScopes)) {
            if (!$isInternal) {
                throw new ScopeUnauthorizedException('Wildcard scope (*) is reserved for internal developers.');
            }
            $wildcardScope = ApiScope::firstOrCreate(
                ['name' => '*'],
                ['resource' => '*', 'action' => '*', 'description' => 'Full access wildcard']
            );
            return [$wildcardScope->id];
        }

        // If client, check all scopes against tier
        if (!$isInternal) {
            $tierScopeNames = $tier->scopes()->pluck('name')->all();
            foreach ($requestedScopes as $scope) {
                if (!in_array($scope, $tierScopeNames)) {
                    throw new ScopeUnauthorizedException("Scope '{$scope}' is not permitted by your tier ({$tier->name}).");
                }
            }
        }

        // Public key restriction check
        if ($keyType === ApiKeyType::Public) {
            $forbidden = $keyType->forbiddenScopes();
            foreach ($requestedScopes as $scope) {
                if (in_array($scope, $forbidden)) {
                    throw new ScopeUnauthorizedException("Scope '{$scope}' cannot be assigned to public keys.");
                }
            }
        }

        // Fetch scope IDs
        return ApiScope::whereIn('name', $requestedScopes)->pluck('id')->all();
    }

    /**
     * Rotate an API key by generating a new plaintext secret/public key and updating the hash.
     *
     * @return array{api_key: ApiKey, plain_text_key: string}
     */
    public function rotateKey(ApiKey $apiKey): array
    {
        $keyType = $apiKey->key_type instanceof ApiKeyType ? $apiKey->key_type : ApiKeyType::from($apiKey->key_type);
        $plainTextKey = $this->generatePlainTextKey($keyType);
        $hashedKey = hash('sha256', $plainTextKey);

        $apiKey->update([
            'key' => $hashedKey,
            'plain_text_prefix' => substr($plainTextKey, 0, 12),
        ]);

        return [
            'api_key' => $apiKey->fresh(),
            'plain_text_key' => $plainTextKey,
        ];
    }

    /**
     * Generate a cryptographically secure random plaintext API key.
     */
    private function generatePlainTextKey(ApiKeyType $keyType): string
    {
        return $keyType->prefix() . Str::random(32);
    }
}
