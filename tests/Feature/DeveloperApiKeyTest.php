<?php

namespace Tests\Feature;

use App\Enums\ApiKeyType;
use App\Models\ApiKey;
use App\Models\ApiTrace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Developer API key management endpoints:
 *   - GET    /api/v1/developer/api-keys
 *   - POST   /api/v1/developer/api-keys
 *   - GET    /api/v1/developer/api-keys/{id}
 *   - PATCH  /api/v1/developer/api-keys/{id}
 *   - DELETE /api/v1/developer/api-keys/{id}
 *   - POST   /api/v1/developer/api-keys/{id}/rotate
 *   - GET    /api/v1/developer/traces
 *   - GET    /api/v1/developer/traces/{traceId}
 */
class DeveloperApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user']);
    }

    /** @test */
    public function developer_can_list_owned_api_keys(): void
    {
        $otherUser = User::factory()->create();

        // Own key
        ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'My Web App Key',
            'key' => hash('sha256', 'lb_pub_mykey1234567890123456789012'),
            'plain_text_prefix' => 'lb_pub_',
            'key_type' => ApiKeyType::Public,
            'scopes' => ['leads:read'],
            'is_active' => true,
        ]);

        // Other user's key
        ApiKey::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Key',
            'key' => hash('sha256', 'lb_sec_other1234567890123456789012'),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['*'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/developer/api-keys');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Web App Key')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'key_type', 'prefix', 'scopes', 'tier', 'is_active', 'created_at']
                ],
                'meta' => ['total', 'max_keys', 'tier']
            ]);
    }

    /** @test */
    public function developer_can_create_public_api_key(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/developer/api-keys', [
                'name' => 'Mobile App Public Key',
                'key_type' => 'public',
                'scopes' => ['leads:read', 'stats:read'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Mobile App Public Key')
            ->assertJsonPath('data.key_type', 'public');
        $this->assertStringStartsWith('lb_pub_', $response->json('data.prefix'));

        $plainKey = $response->json('plain_text_key');
        $this->assertStringStartsWith('lb_pub_', $plainKey);

        // Ensure key hash is stored, not plaintext
        $this->assertDatabaseMissing('api_keys', ['key' => $plainKey]);
        $this->assertDatabaseHas('api_keys', [
            'name' => 'Mobile App Public Key',
            'key' => hash('sha256', $plainKey),
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function developer_can_create_secret_api_key_with_full_access(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/developer/api-keys', [
                'name' => 'Backend Sync Worker',
                'key_type' => 'secret',
                'scopes' => ['*'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Backend Sync Worker')
            ->assertJsonPath('data.key_type', 'secret');
        $this->assertStringStartsWith('lb_sec_', $response->json('data.prefix'));

        $plainKey = $response->json('plain_text_key');
        $this->assertStringStartsWith('lb_sec_', $plainKey);
    }

    /** @test */
    public function developer_cannot_create_key_with_invalid_scopes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/developer/api-keys', [
                'name' => 'Bad Scope Key',
                'key_type' => 'secret',
                'scopes' => ['leads:explode', 'database:drop'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'invalid_scopes');
    }

    /** @test */
    public function developer_can_view_single_owned_api_key(): void
    {
        $key = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Test Key For View',
            'key' => hash('sha256', 'lb_sec_testview1234567890123456789'),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['leads:read'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/developer/api-keys/{$key->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $key->id)
            ->assertJsonPath('data.name', 'Test Key For View')
            ->assertJsonStructure(['data' => ['allowed_ips', 'metadata', 'usage_count']]);
    }

    /** @test */
    public function developer_cannot_view_another_users_key(): void
    {
        $otherUser = User::factory()->create();
        $otherKey = ApiKey::create([
            'user_id' => $otherUser->id,
            'name' => 'Private Other Key',
            'key' => hash('sha256', 'lb_sec_private1234567890123456789'),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['*'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/developer/api-keys/{$otherKey->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function developer_can_update_key_name_and_active_status(): void
    {
        $key = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
            'key' => hash('sha256', 'lb_sec_update123456789012345678901'),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['leads:read'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/developer/api-keys/{$key->id}", [
                'name' => 'Renamed Key',
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed Key')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('api_keys', [
            'id' => $key->id,
            'name' => 'Renamed Key',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function developer_can_rotate_api_key(): void
    {
        $oldPlainKey = 'lb_sec_initial1234567890123456789012';
        $key = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Rotate Me',
            'key' => hash('sha256', $oldPlainKey),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['leads:read'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/developer/api-keys/{$key->id}/rotate");

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data', 'plain_text_key', 'warning']);

        $newPlainKey = $response->json('plain_text_key');
        $this->assertNotEquals($oldPlainKey, $newPlainKey);
        $this->assertStringStartsWith('lb_sec_', $newPlainKey);

        // Old key should no longer exist in hash lookup
        $this->assertDatabaseMissing('api_keys', ['key' => hash('sha256', $oldPlainKey)]);
        $this->assertDatabaseHas('api_keys', ['key' => hash('sha256', $newPlainKey)]);
    }

    /** @test */
    public function developer_can_revoke_api_key(): void
    {
        $key = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'To Be Revoked',
            'key' => hash('sha256', 'lb_sec_revoke1234567890123456789012'),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['leads:read'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/developer/api-keys/{$key->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'API key revoked and deleted successfully.');

        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }

    /** @test */
    public function developer_can_query_trace_logs_for_owned_keys(): void
    {
        $key = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Trace Key',
            'key' => hash('sha256', 'lb_sec_trace12345678901234567890123'),
            'plain_text_prefix' => 'lb_sec_',
            'key_type' => ApiKeyType::Secret,
            'scopes' => ['leads:read'],
            'is_active' => true,
        ]);

        // Create trace for user's key
        ApiTrace::create([
            'trace_id' => fake()->uuid(),
            'api_key_id' => $key->id,
            'user_id' => $this->user->id,
            'method' => 'GET',
            'path' => 'api/v1/leads',
            'status_code' => 200,
            'duration_ms' => 45,
            'ip_address' => '127.0.0.1',
        ]);

        // Create trace for other user
        $otherUser = User::factory()->create();
        ApiTrace::create([
            'trace_id' => fake()->uuid(),
            'user_id' => $otherUser->id,
            'method' => 'GET',
            'path' => 'api/v1/leads',
            'status_code' => 200,
            'duration_ms' => 60,
            'ip_address' => '10.0.0.1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/developer/traces');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ip_address', '127.0.0.1');
    }

    /** @test */
    public function developer_can_query_single_trace_by_uuid(): void
    {
        $traceUuid = fake()->uuid();
        ApiTrace::create([
            'trace_id' => $traceUuid,
            'user_id' => $this->user->id,
            'method' => 'POST',
            'path' => 'api/v1/leads',
            'status_code' => 201,
            'duration_ms' => 85,
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/developer/traces/{$traceUuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.trace_id', $traceUuid)
            ->assertJsonPath('data.status_code', 201);
    }
}
