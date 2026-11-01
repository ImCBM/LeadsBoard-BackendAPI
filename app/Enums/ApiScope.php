<?php

namespace App\Enums;

/**
 * Defines all supported granular API scopes.
 *
 * Format: resource:action
 * Wildcard: '*' grants all scopes.
 * Resource wildcard: 'leads:*' grants all actions on leads.
 */
enum ApiScope: string
{
    // ── Leads ──
    case LeadsRead = 'leads:read';
    case LeadsWrite = 'leads:write';
    case LeadsDelete = 'leads:delete';
    case LeadsExport = 'leads:export';
    case LeadsImport = 'leads:import';

    // ── Tags ──
    case TagsRead = 'tags:read';
    case TagsWrite = 'tags:write';
    case TagsDelete = 'tags:delete';

    // ── Stats ──
    case StatsRead = 'stats:read';

    // ── Webhooks ──
    case WebhooksPost = 'webhooks:post';

    /**
     * Full-access wildcard value.
     */
    public const WILDCARD = '*';

    /**
     * Get all scope values as a flat array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given scope string is valid (including wildcards).
     */
    public static function isValid(string $scope): bool
    {
        if ($scope === self::WILDCARD) {
            return true;
        }

        // Resource wildcard, e.g. "leads:*"
        if (str_ends_with($scope, ':*')) {
            $resource = str_replace(':*', '', $scope);
            return in_array($resource, self::resources());
        }

        return in_array($scope, self::values());
    }

    /**
     * Get unique resource names (leads, tags, stats, webhooks).
     */
    public static function resources(): array
    {
        $resources = [];
        foreach (self::cases() as $case) {
            [$resource] = explode(':', $case->value);
            $resources[$resource] = true;
        }
        return array_keys($resources);
    }

    /**
     * Resolve a preset name into its constituent scopes.
     */
    public static function resolvePreset(string $preset): ?array
    {
        return match ($preset) {
            'read-only' => [
                self::LeadsRead->value,
                self::TagsRead->value,
                self::StatsRead->value,
            ],
            'write-only' => [
                self::LeadsWrite->value,
                self::LeadsDelete->value,
                self::LeadsImport->value,
                self::TagsWrite->value,
                self::TagsDelete->value,
                self::WebhooksPost->value,
            ],
            'full' => [self::WILDCARD],
            default => null,
        };
    }

    /**
     * Check if a set of granted scopes satisfies the required scope.
     */
    public static function scopesSatisfy(array $grantedScopes, string $requiredScope): bool
    {
        // Wildcard grants everything
        if (in_array(self::WILDCARD, $grantedScopes)) {
            return true;
        }

        // Exact match
        if (in_array($requiredScope, $grantedScopes)) {
            return true;
        }

        // Resource wildcard match: "leads:*" satisfies "leads:read"
        [$requiredResource] = explode(':', $requiredScope);
        if (in_array("{$requiredResource}:*", $grantedScopes)) {
            return true;
        }

        return false;
    }
}
