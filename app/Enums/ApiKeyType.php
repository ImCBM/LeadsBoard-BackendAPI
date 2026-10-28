<?php

namespace App\Enums;

/**
 * Defines the type of API key.
 *
 * - public:  Client-safe keys (lb_pub_...) restricted to read-only scopes.
 * - secret:  Server-side keys (lb_sec_...) allowed full read/write/delete.
 */
enum ApiKeyType: string
{
    case Public = 'public';
    case Secret = 'secret';

    /**
     * Get the key prefix used when generating plaintext keys.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Public => 'lb_pub_',
            self::Secret => 'lb_sec_',
        };
    }

    /**
     * Determine if this key type can perform write/delete mutations.
     */
    public function canMutate(): bool
    {
        return match ($this) {
            self::Public => false,
            self::Secret => true,
        };
    }

    /**
     * Get scopes that are explicitly forbidden for this key type.
     */
    public function forbiddenScopes(): array
    {
        return match ($this) {
            self::Public => [
                'leads:write',
                'leads:delete',
                'tags:write',
                'tags:delete',
                'webhooks:post',
            ],
            self::Secret => [],
        };
    }
}
