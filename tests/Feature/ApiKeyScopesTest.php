<?php

namespace Tests\Feature;

use App\Enums\ApiKeyType;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the new API key scope and key type system.
 *
 * Covers:
 * - Public key (lb_pub_) read access
 * - Public key write rejection
 * - Secret key (lb_sec_) scoped access
 * - Wildcard scope (*) access
 * - Resource wildcard (leads:*) access
 * - Missing scope rejection
 * - IP whitelist enforcement
 * - Multi-auth (Sanctum vs API Key) routing
 */
class ApiKeyScopesTest extends TestCase
{
    use RefreshDatabase;

    private function createApiKey(array $overrides = []): array
    {
        $plainKey = ($overrides['key_type'] ?? 'secret') === 'public'
            ? 'lb_pub_' . fake()->regexify('[a-zA-Z0-9]{32}')
            : 'lb_sec_' . fake()->regexify('[a-zA-Z0-9]{32}');

        $defaults = [
            'name' => 'Test Key',
            'key_type' => 'secret',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => substr($plainKey, 0, 12),
            'scopes' => ['*'],
            'tier' => 'pro',
            'rate_limit_per_minute' => 0,
            'is_active' => true,
        ];

        $apiKey = ApiKey::create(array_merge($defaults, $overrides));

        return ['api_key' => $apiKey, 'plain_key' => $plainKey];
    }

    // ── Public Key Tests ───────────────────────────────────────────

    /** @test */
    public function public_key_can_read_leads(): void
    {
        $result = $this->createApiKey([
            'key_type' => 'public',
            'scopes' => ['leads:read', 'tags:read', 'stats:read'],
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Trace-Id');
    }

    /** @test */
    public function public_key_cannot_create_leads(): void
    {
        $result = $this->createApiKey([
            'key_type' => 'public',
            'scopes' => ['leads:read', 'leads:write'], // Even if scopes include write
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'full_name' => 'Test Lead',
        ], [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    /** @test */
    public function public_key_cannot_delete_leads(): void
    {
        $result = $this->createApiKey([
            'key_type' => 'public',
            'scopes' => ['*'], // Even with wildcard
        ]);

        $response = $this->deleteJson('/api/v1/leads/1', [], [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    // ── Secret Key Scope Tests ─────────────────────────────────────

    /** @test */
    public function secret_key_with_leads_read_can_list_leads(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['leads:read'],
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function secret_key_without_leads_read_cannot_list_leads(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['tags:read', 'stats:read'],
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'insufficient_scope']);
    }

    /** @test */
    public function secret_key_with_wildcard_can_access_everything(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['*'],
        ]);

        $this->getJson('/api/v1/leads', ['X-API-Key' => $result['plain_key']])
            ->assertStatus(200);

        $this->getJson('/api/v1/tags', ['X-API-Key' => $result['plain_key']])
            ->assertStatus(200);

        $this->getJson('/api/v1/stats/summary', ['X-API-Key' => $result['plain_key']])
            ->assertStatus(200);
    }

    /** @test */
    public function secret_key_with_resource_wildcard_can_access_all_resource_actions(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['leads:*'],
        ]);

        $this->getJson('/api/v1/leads', ['X-API-Key' => $result['plain_key']])
            ->assertStatus(200);
    }

    // ── Auth Validation Tests ──────────────────────────────────────

    /** @test */
    public function invalid_api_key_returns_401(): void
    {
        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => 'lb_sec_invalid_key_that_does_not_exist',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'invalid_api_key']);
    }

    /** @test */
    public function expired_api_key_returns_401(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['*'],
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function deactivated_api_key_returns_401(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['*'],
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function no_auth_at_all_returns_401(): void
    {
        $response = $this->getJson('/api/v1/leads');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'unauthenticated']);
    }

    /** @test */
    public function sanctum_user_can_access_all_endpoints(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/leads');

        $response->assertStatus(200);
    }

    // ── IP Whitelist Tests ─────────────────────────────────────────

    /** @test */
    public function api_key_with_ip_whitelist_rejects_other_ips(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['*'],
            'allowed_ips' => ['10.0.0.1'],
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        // In tests, the request comes from 127.0.0.1, which is not in the whitelist
        $response->assertStatus(403);
        $response->assertJson(['error' => 'ip_not_allowed']);
    }

    // ── Key via Bearer Token Tests ─────────────────────────────────

    /** @test */
    public function api_key_can_be_passed_via_bearer_token(): void
    {
        $result = $this->createApiKey([
            'scopes' => ['leads:read'],
        ]);

        $response = $this->getJson('/api/v1/leads', [
            'Authorization' => 'Bearer ' . $result['plain_key'],
        ]);

        $response->assertStatus(200);
    }
}
