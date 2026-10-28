<?php

namespace App\Enums;

/**
 * Account tiers for rate limiting and feature gating.
 *
 * Tier definitions live in config/tiers.php.
 * This enum provides type safety and default values.
 */
enum AccountTier: string
{
    case Guest = 'guest';
    case Free = 'free';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    /**
     * The default tier assigned to new API keys without explicit assignment.
     */
    public const DEFAULT = 'free';
}
