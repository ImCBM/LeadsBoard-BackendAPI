<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\TierFieldRestriction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the normalized 3NF database schema.
 *
 * Covers:
 * - Table existence and column structure
 * - Foreign key relationships and constraints
 * - Pivot table relationships (tier_scopes, api_key_scopes)
 * - Model relationship methods
 * - User → Subscription → Tier → Scopes chain
 * - Tier field restrictions
 *
 * TDD RED phase: These tests define the contract that migrations
 * and models must satisfy.
 */
class NormalizedSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ── Table Existence ──────────────────────────────────────────

    #[Test]
    public function tiers_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tiers'));
        $this->assertTrue(Schema::hasColumns('tiers', [
            'id', 'slug', 'name', 'rate_limit_per_minute',
            'daily_request_limit', 'max_keys',
            'can_export', 'can_bulk_operations',
            'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function api_scopes_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('api_scopes'));
        $this->assertTrue(Schema::hasColumns('api_scopes', [
            'id', 'name', 'resource', 'action',
            'description', 'is_mutation',
            'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function tier_scopes_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('tier_scopes'));
        $this->assertTrue(Schema::hasColumns('tier_scopes', [
            'id', 'tier_id', 'api_scope_id',
        ]));
    }

    #[Test]
    public function tier_field_restrictions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('tier_field_restrictions'));
        $this->assertTrue(Schema::hasColumns('tier_field_restrictions', [
            'id', 'tier_id', 'resource', 'field_name',
            'restriction_type', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function subscriptions_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('subscriptions'));
        $this->assertTrue(Schema::hasColumns('subscriptions', [
            'id', 'user_id', 'tier_id', 'status',
            'starts_at', 'ends_at',
            'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function api_keys_table_has_tier_id_foreign_key(): void
    {
        $this->assertTrue(Schema::hasTable('api_keys'));
        $this->assertTrue(Schema::hasColumns('api_keys', [
            'id', 'user_id', 'tier_id', 'name', 'key_type',
            'key', 'plain_text_prefix', 'rate_limit_per_minute',
            'allowed_ips', 'is_active', 'last_used_at',
            'expires_at', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function api_key_scopes_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('api_key_scopes'));
        $this->assertTrue(Schema::hasColumns('api_key_scopes', [
            'id', 'api_key_id', 'api_scope_id',
        ]));
    }

    #[Test]
    public function api_traces_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('api_traces'));
        $this->assertTrue(Schema::hasColumns('api_traces', [
            'id', 'api_key_id', 'user_id', 'trace_id',
            'key_prefix', 'method', 'path', 'route_name',
            'status_code', 'duration_ms', 'ip_address',
            'user_agent', 'query_params', 'request_payload', 'created_at',
        ]));
    }

    // ── Model Relationships: Tier ────────────────────────────────

    #[Test]
    public function tier_has_many_scopes_via_pivot(): void
    {
        $tier = Tier::create([
            'slug' => 'test-tier',
            'name' => 'Test Tier',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $scope = ApiScope::create([
            'name' => 'leads:read',
            'resource' => 'leads',
            'action' => 'read',
            'description' => 'Read leads',
            'is_mutation' => false,
        ]);

        $tier->scopes()->attach($scope->id);

        $this->assertCount(1, $tier->fresh()->scopes);
        $this->assertEquals('leads:read', $tier->scopes->first()->name);
    }

    #[Test]
    public function tier_has_field_restrictions(): void
    {
        $tier = Tier::create([
            'slug' => 'free-test',
            'name' => 'Free Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        TierFieldRestriction::create([
            'tier_id' => $tier->id,
            'resource' => 'leads',
            'field_name' => 'contact_number',
            'restriction_type' => 'mask_null',
        ]);

        $this->assertCount(1, $tier->fresh()->fieldRestrictions);
        $this->assertTrue($tier->isFieldRestricted('leads', 'contact_number'));
        $this->assertFalse($tier->isFieldRestricted('leads', 'first_name'));
    }

    #[Test]
    public function tier_has_scope_helper_method(): void
    {
        $tier = Tier::create([
            'slug' => 'scope-test',
            'name' => 'Scope Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $readScope = ApiScope::create([
            'name' => 'leads:read',
            'resource' => 'leads',
            'action' => 'read',
            'description' => 'Read leads',
            'is_mutation' => false,
        ]);

        $tier->scopes()->attach($readScope->id);

        $this->assertTrue($tier->hasScope('leads:read'));
        $this->assertFalse($tier->hasScope('leads:write'));
    }

    // ── Model Relationships: Subscription ────────────────────────

    #[Test]
    public function subscription_belongs_to_user_and_tier(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $tier = Tier::create([
            'slug' => 'pro-sub-test',
            'name' => 'Pro Sub Test',
            'rate_limit_per_minute' => 300,
            'daily_request_limit' => 50000,
            'max_keys' => 10,
            'can_export' => true,
            'can_bulk_operations' => false,
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'tier_id' => $tier->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->assertEquals($user->id, $subscription->user->id);
        $this->assertEquals($tier->id, $subscription->tier->id);
        $this->assertEquals('pro-sub-test', $subscription->tier->slug);
    }

    #[Test]
    public function subscription_active_scope_filters_correctly(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $tier = Tier::create([
            'slug' => 'active-test',
            'name' => 'Active Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        // Active subscription
        Subscription::create([
            'user_id' => $user->id,
            'tier_id' => $tier->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Cancelled subscription
        Subscription::create([
            'user_id' => $user->id,
            'tier_id' => $tier->id,
            'status' => 'cancelled',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertCount(1, Subscription::active()->get());
    }

    // ── Model Relationships: User ────────────────────────────────

    #[Test]
    public function user_get_tier_returns_active_subscription_tier(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $proTier = Tier::create([
            'slug' => 'pro',
            'name' => 'Pro / Growth',
            'rate_limit_per_minute' => 300,
            'daily_request_limit' => 50000,
            'max_keys' => 10,
            'can_export' => true,
            'can_bulk_operations' => false,
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'tier_id' => $proTier->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $tier = $user->fresh()->getTier();

        $this->assertInstanceOf(Tier::class, $tier);
        $this->assertEquals('pro', $tier->slug);
    }

    #[Test]
    public function user_get_tier_falls_back_to_free_when_no_subscription(): void
    {
        // Must have a 'free' tier in the database for fallback
        Tier::create([
            'slug' => 'free',
            'name' => 'Free Client',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $user = User::factory()->create(['role' => 'client']);
        $tier = $user->getTier();

        $this->assertInstanceOf(Tier::class, $tier);
        $this->assertEquals('free', $tier->slug);
    }

    #[Test]
    public function user_is_internal_developer_check(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $developer = User::factory()->create(['role' => 'developer']);
        $client = User::factory()->create(['role' => 'client']);

        $this->assertTrue($admin->isInternalDeveloper());
        $this->assertTrue($developer->isInternalDeveloper());
        $this->assertFalse($client->isInternalDeveloper());
    }

    // ── Model Relationships: ApiKey ──────────────────────────────

    #[Test]
    public function api_key_belongs_to_tier_model(): void
    {
        $tier = Tier::create([
            'slug' => 'key-tier-test',
            'name' => 'Key Tier Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $apiKey = ApiKey::create([
            'name' => 'Test Key',
            'key_type' => 'secret',
            'key' => hash('sha256', 'lb_sec_test123456789012345678901234'),
            'plain_text_prefix' => 'lb_sec_test1',
            'tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Tier::class, $apiKey->tier);
        $this->assertEquals('key-tier-test', $apiKey->tier->slug);
    }

    #[Test]
    public function api_key_has_scopes_via_pivot(): void
    {
        $tier = Tier::create([
            'slug' => 'key-scope-test',
            'name' => 'Key Scope Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $scope1 = ApiScope::create([
            'name' => 'leads:read',
            'resource' => 'leads',
            'action' => 'read',
            'description' => 'Read leads',
            'is_mutation' => false,
        ]);
        $scope2 = ApiScope::create([
            'name' => 'tags:read',
            'resource' => 'tags',
            'action' => 'read',
            'description' => 'Read tags',
            'is_mutation' => false,
        ]);

        $apiKey = ApiKey::create([
            'name' => 'Scoped Key',
            'key_type' => 'secret',
            'key' => hash('sha256', 'lb_sec_scoped123456789012345678901'),
            'plain_text_prefix' => 'lb_sec_scope',
            'tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $apiKey->scopes()->attach([$scope1->id, $scope2->id]);

        $this->assertCount(2, $apiKey->fresh()->scopes);
        $this->assertTrue($apiKey->hasScope('leads:read'));
        $this->assertTrue($apiKey->hasScope('tags:read'));
        $this->assertFalse($apiKey->hasScope('leads:write'));
    }

    // ── Foreign Key Constraints ──────────────────────────────────

    #[Test]
    public function deleting_user_cascades_to_subscriptions(): void
    {
        $tier = Tier::create([
            'slug' => 'cascade-test',
            'name' => 'Cascade Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $user = User::factory()->create(['role' => 'client']);

        Subscription::create([
            'user_id' => $user->id,
            'tier_id' => $tier->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('subscriptions', ['user_id' => $user->id]);
    }

    #[Test]
    public function deleting_user_cascades_to_api_keys(): void
    {
        $tier = Tier::create([
            'slug' => 'key-cascade-test',
            'name' => 'Key Cascade Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $user = User::factory()->create(['role' => 'client']);

        ApiKey::create([
            'user_id' => $user->id,
            'name' => 'User Key',
            'key_type' => 'secret',
            'key' => hash('sha256', 'lb_sec_userkey12345678901234567890'),
            'plain_text_prefix' => 'lb_sec_userk',
            'tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('api_keys', ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('api_keys', ['user_id' => $user->id]);
    }

    #[Test]
    public function tier_slug_is_unique(): void
    {
        Tier::create([
            'slug' => 'unique-test',
            'name' => 'Unique Test',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Tier::create([
            'slug' => 'unique-test',
            'name' => 'Duplicate Slug',
            'rate_limit_per_minute' => 120,
            'daily_request_limit' => 10000,
            'max_keys' => 5,
            'can_export' => true,
            'can_bulk_operations' => true,
        ]);
    }

    #[Test]
    public function api_scope_name_is_unique(): void
    {
        ApiScope::create([
            'name' => 'leads:read',
            'resource' => 'leads',
            'action' => 'read',
            'description' => 'Read leads',
            'is_mutation' => false,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ApiScope::create([
            'name' => 'leads:read',
            'resource' => 'leads',
            'action' => 'read',
            'description' => 'Duplicate',
            'is_mutation' => false,
        ]);
    }

    // ── Full Relationship Chain ──────────────────────────────────

    #[Test]
    public function full_chain_user_to_subscription_to_tier_to_scopes(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $tier = Tier::create([
            'slug' => 'chain-test',
            'name' => 'Chain Test Tier',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'can_export' => false,
            'can_bulk_operations' => false,
        ]);

        $scope1 = ApiScope::create([
            'name' => 'leads:read',
            'resource' => 'leads',
            'action' => 'read',
            'description' => 'Read leads',
            'is_mutation' => false,
        ]);
        $scope2 = ApiScope::create([
            'name' => 'stats:read',
            'resource' => 'stats',
            'action' => 'read',
            'description' => 'Read stats',
            'is_mutation' => false,
        ]);

        $tier->scopes()->attach([$scope1->id, $scope2->id]);

        Subscription::create([
            'user_id' => $user->id,
            'tier_id' => $tier->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Traverse the full chain
        $userTier = $user->fresh()->getTier();
        $this->assertEquals('chain-test', $userTier->slug);

        $tierScopes = $userTier->scopes;
        $this->assertCount(2, $tierScopes);

        $scopeNames = $tierScopes->pluck('name')->toArray();
        $this->assertContains('leads:read', $scopeNames);
        $this->assertContains('stats:read', $scopeNames);

        // Verify tier helpers
        $this->assertTrue($userTier->hasScope('leads:read'));
        $this->assertFalse($userTier->hasScope('leads:write'));
    }
}
