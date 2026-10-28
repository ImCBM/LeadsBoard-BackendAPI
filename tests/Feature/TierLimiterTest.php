<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Services\TierLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the TierLimiter service and rate limiting.
 *
 * Covers:
 * - Rate limit resolution (key override > tier default > system fallback)
 * - Tier capability checks (export, bulk operations)
 * - Tier scope restrictions
 * - Rate limit enforcement (429 responses)
 * - Rate limit headers in responses
 */
class TierLimiterTest extends TestCase
{
    use RefreshDatabase;

    private function createApiKey(array $overrides = []): array
    {
        $plainKey = 'lb_sec_' . fake()->regexify('[a-zA-Z0-9]{32}');

        $defaults = [
            'name' => 'Tier Test Key',
            'key_type' => 'secret',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => substr($plainKey, 0, 12),
            'scopes' => ['*'],
            'tier' => 'free',
            'rate_limit_per_minute' => null,
            'is_active' => true,
        ];

        $apiKey = ApiKey::create(array_merge($defaults, $overrides));

        return ['api_key' => $apiKey, 'plain_key' => $plainKey];
    }

    /** @test */
    public function tier_limiter_resolves_custom_key_rate_limit(): void
    {
        $limiter = app(TierLimiter::class);

        $result = $this->createApiKey([
            'rate_limit_per_minute' => 500,
            'tier' => 'free', // Free tier default is 60
        ]);

        $rateLimit = $limiter->resolveRateLimit($result['api_key']);
        $this->assertEquals(500, $rateLimit);
    }

    /** @test */
    public function tier_limiter_falls_back_to_tier_default(): void
    {
        $limiter = app(TierLimiter::class);

        $result = $this->createApiKey([
            'rate_limit_per_minute' => null,
            'tier' => 'free',
        ]);

        $rateLimit = $limiter->resolveRateLimit($result['api_key']);
        $this->assertEquals(60, $rateLimit); // Free tier = 60 req/min
    }

    /** @test */
    public function tier_limiter_respects_unlimited_key_override(): void
    {
        $limiter = app(TierLimiter::class);

        $result = $this->createApiKey([
            'rate_limit_per_minute' => 0, // Unlimited override
            'tier' => 'guest',
        ]);

        $rateLimit = $limiter->resolveRateLimit($result['api_key']);
        $this->assertEquals(0, $rateLimit);
    }

    /** @test */
    public function tier_allows_export_for_pro(): void
    {
        $limiter = app(TierLimiter::class);
        $this->assertTrue($limiter->tierAllows('pro', 'export'));
    }

    /** @test */
    public function tier_denies_export_for_guest(): void
    {
        $limiter = app(TierLimiter::class);
        $this->assertFalse($limiter->tierAllows('guest', 'export'));
    }

    /** @test */
    public function tier_allows_bulk_operations_for_enterprise(): void
    {
        $limiter = app(TierLimiter::class);
        $this->assertTrue($limiter->tierAllows('enterprise', 'bulk_operations'));
    }

    /** @test */
    public function tier_denies_bulk_operations_for_free(): void
    {
        $limiter = app(TierLimiter::class);
        $this->assertFalse($limiter->tierAllows('free', 'bulk_operations'));
    }

    /** @test */
    public function guest_tier_only_allows_read_scopes(): void
    {
        $limiter = app(TierLimiter::class);

        $this->assertTrue($limiter->tierAllowsScope('guest', 'leads:read'));
        $this->assertTrue($limiter->tierAllowsScope('guest', 'stats:read'));
        $this->assertTrue($limiter->tierAllowsScope('guest', 'tags:read'));
        $this->assertFalse($limiter->tierAllowsScope('guest', 'leads:write'));
        $this->assertFalse($limiter->tierAllowsScope('guest', 'leads:delete'));
    }

    /** @test */
    public function pro_tier_allows_all_scopes_via_wildcard(): void
    {
        $limiter = app(TierLimiter::class);

        $this->assertTrue($limiter->tierAllowsScope('pro', 'leads:read'));
        $this->assertTrue($limiter->tierAllowsScope('pro', 'leads:write'));
        $this->assertTrue($limiter->tierAllowsScope('pro', 'leads:delete'));
        $this->assertTrue($limiter->tierAllowsScope('pro', 'webhooks:post'));
    }

    /** @test */
    public function rate_limit_exceeded_returns_429(): void
    {
        $result = $this->createApiKey([
            'rate_limit_per_minute' => 2,
            'tier' => 'guest',
        ]);

        // Make requests up to the limit
        $this->getJson('/api/v1/leads', ['X-API-Key' => $result['plain_key']])->assertStatus(200);
        $this->getJson('/api/v1/leads', ['X-API-Key' => $result['plain_key']])->assertStatus(200);

        // This should exceed the limit
        $response = $this->getJson('/api/v1/leads', ['X-API-Key' => $result['plain_key']]);
        $response->assertStatus(429);
        $response->assertJson(['error' => 'rate_limit_exceeded']);
        $response->assertHeader('Retry-After');
        $response->assertHeader('X-RateLimit-Limit', 2);
    }

    /** @test */
    public function responses_include_rate_limit_headers(): void
    {
        $result = $this->createApiKey([
            'rate_limit_per_minute' => 100,
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit', 100);
        $response->assertHeader('X-RateLimit-Remaining');
    }
}
