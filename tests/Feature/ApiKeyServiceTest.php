<?php

namespace Tests\Feature;

use App\Enums\ApiKeyType;
use App\Exceptions\ApiKeyQuotaExceededException;
use App\Exceptions\ScopeUnauthorizedException;
use App\Exceptions\TierUnauthorizedException;
use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Tier;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for ApiKeyService under normalized 3NF architecture.
 *
 * Covers:
 * - Entitlement gating (Clients cannot exceed their tier ceiling or assign ungranted scopes)
 * - Internal developer / Admin privileged key creation
 * - Key budgeting (Key limit <= Tier rate limit)
 * - Maximum keys quota enforcement per tier
 * - Cryptographic key generation (lb_sec_ / lb_pub_ prefixes, SHA-256 storage)
 *
 * TDD RED phase: Defines the contract for the updated ApiKeyService.
 */
class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = app(ApiKeyService::class);
    }

    #[Test]
    public function client_key_automatically_assigns_user_active_tier(): void
    {
        $proUser = User::where('email', 'pro@leadsboard.local')->first();

        $result = $this->service->createKey([
            'name' => 'My Pro Integration',
            'key_type' => 'secret',
            'scopes' => ['leads:read', 'leads:write'],
        ], $proUser);

        $apiKey = $result['api_key'];
        $this->assertEquals('pro', $apiKey->tier->slug);
        $this->assertTrue(str_starts_with($result['plain_text_key'], 'lb_sec_'));
        $this->assertEquals(hash('sha256', $result['plain_text_key']), $apiKey->key);
    }

    #[Test]
    public function client_cannot_request_tier_higher_than_subscription(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        $enterpriseTier = Tier::where('slug', 'enterprise')->first();

        $this->expectException(TierUnauthorizedException::class);

        $this->service->createKey([
            'name' => 'Cheating Key',
            'key_type' => 'secret',
            'tier_id' => $enterpriseTier->id,
            'scopes' => ['leads:read'],
        ], $freeUser);
    }

    #[Test]
    public function client_cannot_request_scopes_outside_tier_entitlement(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();

        // Free tier does NOT have leads:write
        $this->expectException(ScopeUnauthorizedException::class);

        $this->service->createKey([
            'name' => 'Attempted Write Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read', 'leads:write'],
        ], $freeUser);
    }

    #[Test]
    public function client_key_budget_cannot_exceed_tier_rate_limit(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        // Free tier rate limit is 60 req/min

        $this->expectException(ValidationException::class);

        $this->service->createKey([
            'name' => 'Overbudget Key',
            'key_type' => 'secret',
            'rate_limit_per_minute' => 120, // Exceeds tier limit of 60
            'scopes' => ['leads:read'],
        ], $freeUser);
    }

    #[Test]
    public function key_limit_quota_enforced_against_tier_max_keys(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        // Free tier allows max 3 keys

        // Create 3 keys
        for ($i = 1; $i <= 3; $i++) {
            $this->service->createKey([
                'name' => "Key {$i}",
                'key_type' => 'secret',
                'scopes' => ['leads:read'],
            ], $freeUser);
        }

        // 4th key must throw ApiKeyQuotaExceededException
        $this->expectException(ApiKeyQuotaExceededException::class);

        $this->service->createKey([
            'name' => 'Key 4 (Exceeded)',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $freeUser);
    }

    #[Test]
    public function internal_developer_can_create_custom_tier_and_wildcard_keys(): void
    {
        $admin = User::where('email', 'admin@leadsboard.local')->first();
        $enterpriseTier = Tier::where('slug', 'enterprise')->first();

        $result = $this->service->createKey([
            'name' => 'Internal Master Automation',
            'key_type' => 'secret',
            'tier_id' => $enterpriseTier->id,
            'rate_limit_per_minute' => 5000, // Custom high limit
            'scopes' => ['*'],
        ], $admin);

        $apiKey = $result['api_key'];
        $this->assertEquals('enterprise', $apiKey->tier->slug);
        $this->assertEquals(5000, $apiKey->rate_limit_per_minute);
        $this->assertTrue($apiKey->hasScope('leads:read'));
        $this->assertTrue($apiKey->hasScope('webhooks:post'));
    }
}
