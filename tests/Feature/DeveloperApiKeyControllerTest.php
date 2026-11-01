<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for Customer Portal API key controller (DeveloperApiKeyController).
 *
 * Verifies that:
 * - GET /api/v1/developer/api-keys returns tier entitlements, active keys, and allowed/locked scopes.
 * - POST /api/v1/developer/api-keys creates a key locked to the user's tier.
 * - POST /api/v1/developer/api-keys returns 403 when user requests forbidden scopes.
 * - POST /api/v1/developer/api-keys returns 422 when user exceeds tier max keys.
 * - DELETE /api/v1/developer/api-keys/{id} revokes key.
 * - POST /api/v1/developer/api-keys/{id}/rotate rotates key.
 *
 * TDD RED phase: Defines the contract for Customer Portal API key management.
 */
class DeveloperApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @test */
    public function customer_can_list_keys_with_tier_metadata(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        Sanctum::actingAs($freeUser);

        $response = $this->getJson('/api/v1/developer/api-keys');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'tier',
                'max_keys',
                'allowed_scopes',
            ],
        ]);

        $this->assertEquals('free', $response->json('meta.tier'));
        $this->assertEquals(3, $response->json('meta.max_keys'));
        $this->assertContains('leads:read', $response->json('meta.allowed_scopes'));
        $this->assertNotContains('leads:write', $response->json('meta.allowed_scopes'));
    }

    /** @test */
    public function customer_can_create_valid_key_within_tier_ceiling(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        Sanctum::actingAs($freeUser);

        $response = $this->postJson('/api/v1/developer/api-keys', [
            'name' => 'Free Read Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ]);

        $response->assertStatus(201);
        $this->assertArrayHasKey('plain_text_key', $response->json());
        $this->assertEquals('free', $response->json('data.tier'));
        $this->assertContains('leads:read', $response->json('data.scopes'));
    }

    /** @test */
    public function customer_cannot_request_scopes_outside_tier_ceiling(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        Sanctum::actingAs($freeUser);

        $response = $this->postJson('/api/v1/developer/api-keys', [
            'name' => 'Cheating Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read', 'leads:write'], // Free cannot have leads:write
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function customer_cannot_exceed_max_keys_limit(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        Sanctum::actingAs($freeUser);

        // Free tier allows 3 keys
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/developer/api-keys', [
                'name' => "Key {$i}",
                'key_type' => 'secret',
                'scopes' => ['leads:read'],
            ])->assertStatus(201);
        }

        // 4th key must be rejected
        $response = $this->postJson('/api/v1/developer/api-keys', [
            'name' => 'Key 4 (Over Limit)',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function customer_can_rotate_own_key(): void
    {
        $proUser = User::where('email', 'pro@leadsboard.local')->first();
        Sanctum::actingAs($proUser);

        $createResponse = $this->postJson('/api/v1/developer/api-keys', [
            'name' => 'Rotatable Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ]);

        $createResponse->assertStatus(201);
        $keyId = $createResponse->json('data.id');
        $oldPlainText = $createResponse->json('plain_text_key');

        $rotateResponse = $this->postJson("/api/v1/developer/api-keys/{$keyId}/rotate");

        $rotateResponse->assertStatus(200);
        $newPlainText = $rotateResponse->json('plain_text_key');

        $this->assertNotEmpty($newPlainText);
        $this->assertNotEquals($oldPlainText, $newPlainText);
    }
}
