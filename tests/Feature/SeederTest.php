<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for database seeders and test persona matrix.
 *
 * Verifies that:
 * - Default tiers are created with exact specifications.
 * - Granular API scopes are registered in api_scopes.
 * - Tier scopes pivot table correctly links tiers to allowed scopes.
 * - Tier field restrictions are registered for free tier.
 * - Persona test matrix accounts are seeded with active subscriptions.
 * - Deterministic n8n automation test webhook key is pre-seeded.
 *
 * TDD RED phase: Defines the contract for TierAndScopeSeeder and TestUserPersonaSeeder.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @test */
    public function default_tiers_are_seeded_with_expected_attributes(): void
    {
        $free = Tier::where('slug', 'free')->first();
        $this->assertNotNull($free);
        $this->assertEquals('Free Client', $free->name);
        $this->assertEquals(60, $free->rate_limit_per_minute);
        $this->assertEquals(5000, $free->daily_request_limit);
        $this->assertEquals(3, $free->max_keys);
        $this->assertFalse($free->can_export);
        $this->assertFalse($free->can_bulk_operations);

        $pro = Tier::where('slug', 'pro')->first();
        $this->assertNotNull($pro);
        $this->assertEquals('Pro / Growth', $pro->name);
        $this->assertEquals(300, $pro->rate_limit_per_minute);
        $this->assertEquals(50000, $pro->daily_request_limit);
        $this->assertEquals(10, $pro->max_keys);
        $this->assertTrue($pro->can_export);
        $this->assertFalse($pro->can_bulk_operations);

        $enterprise = Tier::where('slug', 'enterprise')->first();
        $this->assertNotNull($enterprise);
        $this->assertEquals('Enterprise', $enterprise->name);
        $this->assertEquals(1200, $enterprise->rate_limit_per_minute);
        $this->assertEquals(500000, $enterprise->daily_request_limit);
        $this->assertEquals(50, $enterprise->max_keys);
        $this->assertTrue($enterprise->can_export);
        $this->assertTrue($enterprise->can_bulk_operations);
    }

    /** @test */
    public function all_granular_api_scopes_are_seeded(): void
    {
        $expectedScopes = [
            'leads:read',
            'leads:write',
            'leads:delete',
            'leads:export',
            'leads:import',
            'tags:read',
            'tags:write',
            'tags:delete',
            'stats:read',
            'webhooks:post',
        ];

        foreach ($expectedScopes as $scopeName) {
            $this->assertTrue(
                ApiScope::where('name', $scopeName)->exists(),
                "Expected scope {$scopeName} to be seeded."
            );
        }
    }

    /** @test */
    public function tiers_have_correct_scope_assignments(): void
    {
        $free = Tier::where('slug', 'free')->first();
        $this->assertTrue($free->hasScope('leads:read'));
        $this->assertTrue($free->hasScope('tags:read'));
        $this->assertTrue($free->hasScope('stats:read'));
        $this->assertFalse($free->hasScope('leads:write'));
        $this->assertFalse($free->hasScope('leads:export'));
        $this->assertFalse($free->hasScope('webhooks:post'));

        $pro = Tier::where('slug', 'pro')->first();
        $this->assertTrue($pro->hasScope('leads:read'));
        $this->assertTrue($pro->hasScope('leads:write'));
        $this->assertTrue($pro->hasScope('leads:delete'));
        $this->assertTrue($pro->hasScope('leads:export'));
        $this->assertTrue($pro->hasScope('tags:read'));
        $this->assertTrue($pro->hasScope('tags:write'));
        $this->assertTrue($pro->hasScope('tags:delete'));
        $this->assertTrue($pro->hasScope('stats:read'));
        $this->assertFalse($pro->hasScope('leads:import'));
        $this->assertFalse($pro->hasScope('webhooks:post'));

        $enterprise = Tier::where('slug', 'enterprise')->first();
        $this->assertTrue($enterprise->hasScope('leads:read'));
        $this->assertTrue($enterprise->hasScope('leads:write'));
        $this->assertTrue($enterprise->hasScope('leads:delete'));
        $this->assertTrue($enterprise->hasScope('leads:export'));
        $this->assertTrue($enterprise->hasScope('leads:import'));
        $this->assertTrue($enterprise->hasScope('tags:read'));
        $this->assertTrue($enterprise->hasScope('tags:write'));
        $this->assertTrue($enterprise->hasScope('tags:delete'));
        $this->assertTrue($enterprise->hasScope('stats:read'));
        $this->assertTrue($enterprise->hasScope('webhooks:post'));
    }

    /** @test */
    public function free_tier_has_field_restrictions_seeded(): void
    {
        $free = Tier::where('slug', 'free')->first();
        $this->assertTrue($free->isFieldRestricted('leads', 'contact_number'));

        $pro = Tier::where('slug', 'pro')->first();
        $this->assertFalse($pro->isFieldRestricted('leads', 'contact_number'));
    }

    /** @test */
    public function test_persona_users_are_seeded_with_correct_subscriptions(): void
    {
        // Admin persona
        $admin = User::where('email', 'admin@leadsboard.local')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->isInternalDeveloper());

        // Free persona
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        $this->assertNotNull($freeUser);
        $this->assertTrue(Hash::check('password', $freeUser->password));
        $this->assertFalse($freeUser->isInternalDeveloper());
        $this->assertEquals('free', $freeUser->getTier()->slug);

        // Pro persona
        $proUser = User::where('email', 'pro@leadsboard.local')->first();
        $this->assertNotNull($proUser);
        $this->assertTrue(Hash::check('password', $proUser->password));
        $this->assertFalse($proUser->isInternalDeveloper());
        $this->assertEquals('pro', $proUser->getTier()->slug);

        // Enterprise persona
        $enterpriseUser = User::where('email', 'enterprise@leadsboard.local')->first();
        $this->assertNotNull($enterpriseUser);
        $this->assertTrue(Hash::check('password', $enterpriseUser->password));
        $this->assertFalse($enterpriseUser->isInternalDeveloper());
        $this->assertEquals('enterprise', $enterpriseUser->getTier()->slug);
    }

    /** @test */
    public function deterministic_n8n_test_webhook_key_is_seeded(): void
    {
        $rawKey = 'lb_sec_n8n_test_automation_key';
        $hashed = hash('sha256', $rawKey);

        $apiKey = ApiKey::where('key', $hashed)->first();
        $this->assertNotNull($apiKey, 'Deterministic n8n webhook API key must be seeded.');
        $this->assertTrue($apiKey->isValid());
        $this->assertTrue($apiKey->isSecret());
        $this->assertTrue($apiKey->hasScope('webhooks:post'));
        $this->assertEquals('enterprise', $apiKey->tier->slug);
    }
}
