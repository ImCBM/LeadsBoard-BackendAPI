<?php

namespace App\Services;

use App\Enums\ApiKeyType;
use App\Enums\ApiScope;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Service for API key generation, validation, and management.
 *
 * Handles the creation of public/secret key pairs, hashing,
 * scope validation, and key lifecycle operations.
 */
class ApiKeyService
{
    /**
     * Generate a new API key pair (or single key) for a user or standalone use.
     *
     * @param  array{
     *     name: string,
     *     key_type: string,
     *     scopes?: array<string>,
     *     tier?: string,
     *     rate_limit_per_minute?: int|null,
     *     allowed_ips?: array<string>|null,
     *     metadata?: array|null,
     *     expires_at?: string|null,
     * }  $data
     * @param  User|null  $user  The owning user (null for standalone keys)
     * @return array{api_key: ApiKey, plain_text_key: string}
     */
    public function createKey(array $data, ?User $user = null): array
    {
        $keyType = ApiKeyType::from($data['key_type'] ?? 'secret');
        $scopes = $this->resolveScopes($data['scopes'] ?? [], $keyType);

        $plainTextKey = $this->generatePlainTextKey($keyType);
        $hashedKey = hash('sha256', $plainTextKey);

        $apiKey = ApiKey::create([
            'user_id' => $user?->id,
            'name' => $data['name'],
            'key_type' => $keyType,
            'key' => $hashedKey,
            'plain_text_prefix' => substr($plainTextKey, 0, 12), // Includes prefix for identification
            'scopes' => $scopes,
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? null,
            'tier' => $data['tier'] ?? $user?->getTier() ?? config('tiers.default', 'free'),
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'is_active' => true,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return [
            'api_key' => $apiKey,
            'plain_text_key' => $plainTextKey,
        ];
    }

    /**
     * Generate a formatted plaintext key with the appropriate prefix.
     *
     * Format: lb_pub_<32 random chars> or lb_sec_<32 random chars>
     */
    public function generatePlainTextKey(ApiKeyType $keyType): string
    {
        return $keyType->prefix() . Str::random(32);
    }

    /**
     * Resolve scope input (may include presets) into a final scope array.
     * Filters out scopes forbidden for the key type.
     */
    public function resolveScopes(array $requestedScopes, ApiKeyType $keyType): array
    {
        $resolved = [];

        foreach ($requestedScopes as $scope) {
            // Check if it's a preset
            $presetScopes = ApiScope::resolvePreset($scope);
            if ($presetScopes !== null) {
                $resolved = array_merge($resolved, $presetScopes);
                continue;
            }

            // Validate individual scope
            if (ApiScope::isValid($scope)) {
                $resolved[] = $scope;
            }
        }

        $resolved = array_unique($resolved);

        // Filter out forbidden scopes for this key type
        $forbidden = $keyType->forbiddenScopes();
        $resolved = array_values(array_diff($resolved, $forbidden));

        return $resolved;
    }

    /**
     * Validate that the given scopes are valid scope strings.
     *
     * @return array  Invalid scope strings (empty if all valid)
     */
    public function validateScopes(array $scopes): array
    {
        $invalid = [];
        foreach ($scopes as $scope) {
            if (ApiScope::resolvePreset($scope) !== null) {
                continue;
            }
            if (!ApiScope::isValid($scope)) {
                $invalid[] = $scope;
            }
        }
        return $invalid;
    }

    /**
     * Rotate an API key: generate a new key value while preserving all settings.
     *
     * @return array{api_key: ApiKey, plain_text_key: string}
     */
    public function rotateKey(ApiKey $apiKey): array
    {
        $plainTextKey = $this->generatePlainTextKey($apiKey->key_type);
        $hashedKey = hash('sha256', $plainTextKey);

        $apiKey->update([
            'key' => $hashedKey,
            'plain_text_prefix' => substr($plainTextKey, 0, 12),
        ]);

        return [
            'api_key' => $apiKey->refresh(),
            'plain_text_key' => $plainTextKey,
        ];
    }
}
