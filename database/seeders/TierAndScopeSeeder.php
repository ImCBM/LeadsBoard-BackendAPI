<?php

namespace Database\Seeders;

use App\Models\ApiScope;
use App\Models\Tier;
use App\Models\TierFieldRestriction;
use Illuminate\Database\Seeder;

/**
 * Seeds normalized tiers, granular API scopes, tier-scope assignments,
 * and tier-level field restrictions.
 */
class TierAndScopeSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Create Tiers ──────────────────────────────────────────
        $tiersData = [
            'free' => [
                'name' => 'Free Client',
                'rate_limit_per_minute' => 60,
                'daily_request_limit' => 5000,
                'max_keys' => 3,
                'can_export' => false,
                'can_bulk_operations' => false,
            ],
            'pro' => [
                'name' => 'Pro / Growth',
                'rate_limit_per_minute' => 300,
                'daily_request_limit' => 50000,
                'max_keys' => 10,
                'can_export' => true,
                'can_bulk_operations' => false,
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'rate_limit_per_minute' => 1200,
                'daily_request_limit' => 500000,
                'max_keys' => 50,
                'can_export' => true,
                'can_bulk_operations' => true,
            ],
            'developer' => [
                'name' => 'Developer / Internal',
                'rate_limit_per_minute' => 2400,
                'daily_request_limit' => 1000000,
                'max_keys' => 100,
                'can_export' => true,
                'can_bulk_operations' => true,
            ],
        ];

        $tiers = [];
        foreach ($tiersData as $slug => $data) {
            $tiers[$slug] = Tier::updateOrCreate(
                ['slug' => $slug],
                $data
            );
        }

        // ── 2. Create Granular Scopes ─────────────────────────────────
        $scopesData = [
            // Leads
            'leads:read'   => ['resource' => 'leads', 'action' => 'read',   'description' => 'Browse and read leads', 'is_mutation' => false],
            'leads:write'  => ['resource' => 'leads', 'action' => 'write',  'description' => 'Create and update leads', 'is_mutation' => true],
            'leads:delete' => ['resource' => 'leads', 'action' => 'delete', 'description' => 'Delete leads', 'is_mutation' => true],
            'leads:export' => ['resource' => 'leads', 'action' => 'export', 'description' => 'Export leads to CSV/JSON', 'is_mutation' => false],
            'leads:import' => ['resource' => 'leads', 'action' => 'import', 'description' => 'Bulk import leads', 'is_mutation' => true],

            // Tags
            'tags:read'    => ['resource' => 'tags',  'action' => 'read',   'description' => 'Read tags and classifications', 'is_mutation' => false],
            'tags:write'   => ['resource' => 'tags',  'action' => 'write',  'description' => 'Create and update tags', 'is_mutation' => true],
            'tags:delete'  => ['resource' => 'tags',  'action' => 'delete', 'description' => 'Delete tags', 'is_mutation' => true],

            // Stats
            'stats:read'   => ['resource' => 'stats', 'action' => 'read',   'description' => 'Read dashboard summary statistics', 'is_mutation' => false],

            // Webhooks
            'webhooks:post' => ['resource' => 'webhooks', 'action' => 'post', 'description' => 'Post payload to webhook ingestion endpoints', 'is_mutation' => true],
        ];

        $scopes = [];
        foreach ($scopesData as $name => $data) {
            $scopes[$name] = ApiScope::updateOrCreate(
                ['name' => $name],
                $data
            );
        }

        // ── 3. Assign Scopes to Tiers (via tier_scopes pivot) ────────
        // Free: Read-only access
        $freeScopeNames = ['leads:read', 'tags:read', 'stats:read'];
        $tiers['free']->scopes()->sync(
            array_map(fn($s) => $scopes[$s]->id, $freeScopeNames)
        );

        // Pro: Read, write, delete, export
        $proScopeNames = [
            'leads:read', 'leads:write', 'leads:delete', 'leads:export',
            'tags:read', 'tags:write', 'tags:delete',
            'stats:read',
        ];
        $tiers['pro']->scopes()->sync(
            array_map(fn($s) => $scopes[$s]->id, $proScopeNames)
        );

        // Enterprise & Developer: All scopes
        $allScopeIds = array_map(fn($scope) => $scope->id, $scopes);
        $tiers['enterprise']->scopes()->sync($allScopeIds);
        $tiers['developer']->scopes()->sync($allScopeIds);

        // ── 4. Seed Tier Field Restrictions ──────────────────────────
        // Free tier cannot view contact_number
        TierFieldRestriction::updateOrCreate(
            [
                'tier_id'    => $tiers['free']->id,
                'resource'   => 'leads',
                'field_name' => 'contact_number',
            ],
            [
                'restriction_type' => 'mask_null',
            ]
        );
    }
}
