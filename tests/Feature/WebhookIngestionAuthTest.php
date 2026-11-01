<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Lead;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Webhook API Key Unification and simulate:n8n.
 *
 * Verifies that:
 * - All webhook ingestion endpoints use standard Secret API keys with webhooks:post scope.
 * - Deterministic seeded key lb_sec_n8n_test_automation_key successfully authenticates.
 * - Keys without webhooks:post scope return 403 Forbidden.
 * - Public keys (lb_pub_) return 403 Forbidden.
 * - Legacy static .env WEBHOOK_SECRET is cleanly rejected (401).
 *
 * TDD RED phase: Defines the contract for Webhook API key unification.
 */
class WebhookIngestionAuthTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $keyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->keyService = app(ApiKeyService::class);
    }

    #[Test]
    public function deterministic_n8n_key_authenticates_single_lead_webhook(): void
    {
        $payload = [
            'Full Name' => 'Satya Nadella',
            'Job Title' => 'CEO',
            'Corporate Work Email' => 'satya@microsoft.com',
            'Company Name' => 'Microsoft',
            'Clean Root Domain' => 'microsoft.com',
        ];

        $response = $this->withHeader('X-API-Key', 'lb_sec_n8n_test_automation_key')
            ->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'satya@microsoft.com',
        ]);
    }

    #[Test]
    public function key_without_webhook_scope_is_forbidden_on_webhook_endpoints(): void
    {
        $proUser = User::where('email', 'pro@leadsboard.local')->first();

        // Pro key with leads:write (does not have webhooks:post)
        $keyResult = $this->keyService->createKey([
            'name' => 'Pro Standard Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read', 'leads:write'],
        ], $proUser);

        $payload = [
            'Full Name' => 'Tim Cook',
            'Job Title' => 'CEO',
            'Corporate Work Email' => 'tim@apple.com',
            'Company Name' => 'Apple',
            'Clean Root Domain' => 'apple.com',
        ];

        $response = $this->withHeader('X-API-Key', $keyResult['plain_text_key'])
            ->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error' => 'insufficient_scope',
        ]);
    }

    #[Test]
    public function public_key_is_forbidden_on_webhook_endpoints(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();

        $keyResult = $this->keyService->createKey([
            'name' => 'Public Key',
            'key_type' => 'public',
            'scopes' => ['leads:read'],
        ], $freeUser);

        $payload = [
            'Full Name' => 'Sundar Pichai',
            'Job Title' => 'CEO',
            'Corporate Work Email' => 'sundar@google.com',
            'Company Name' => 'Google',
            'Clean Root Domain' => 'google.com',
        ];

        $response = $this->withHeader('X-API-Key', $keyResult['plain_text_key'])
            ->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(403);
    }

    #[Test]
    public function legacy_static_webhook_secret_without_api_key_is_rejected(): void
    {
        // Even if config has a webhook secret, raw static token must not authenticate
        config(['services.webhook.secret' => 'legacy_static_secret_123']);

        $payload = [
            'Full Name' => 'Legacy User',
            'Job Title' => 'Director',
            'Corporate Work Email' => 'legacy@example.com',
            'Company Name' => 'Legacy Corp',
            'Clean Root Domain' => 'example.com',
        ];

        $response = $this->withHeader('Authorization', 'Bearer legacy_static_secret_123')
            ->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(401);
    }

    #[Test]
    public function simulate_n8n_command_defines_api_key_option(): void
    {
        $command = $this->app->make(\App\Console\Commands\SimulateN8nCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('api-key'), 'n8n:simulate command must support --api-key option.');
    }
}
