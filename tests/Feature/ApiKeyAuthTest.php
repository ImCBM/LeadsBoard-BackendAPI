<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Lead;
use App\Services\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_external_route_without_api_key(): void
    {
        $response = $this->getJson('/api/v1/external/leads');

        $response->assertStatus(401)
                 ->assertJsonStructure(['message']);
    }

    public function test_rejects_external_route_with_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'invalid_key_12345678901234567890',
        ])->getJson('/api/v1/external/leads');

        $response->assertStatus(401)
                 ->assertJsonFragment(['message' => 'Invalid, expired, or deactivated API key.']);
    }

    public function test_rejects_external_route_with_inactive_api_key(): void
    {
        $plainKey = 'jb_live_inactivekey1234567890123456';
        ApiKey::create([
            'name' => 'inactive-key',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->getJson('/api/v1/external/leads');

        $response->assertStatus(401)
                 ->assertJsonFragment(['message' => 'Invalid, expired, or deactivated API key.']);
    }

    public function test_allows_access_with_valid_api_key_header(): void
    {
        $plainKey = 'jb_live_testkey12345678901234567890';
        ApiKey::create([
            'name' => 'test-key',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 10,
            'is_active' => true,
        ]);

        app(LeadIngestionService::class)->ingest([
            'full_name' => 'API Key Lead',
            'corporate_email' => 'apikey@example.com',
            'company_name' => 'Key Corp',
            'title_tier' => 'C-Level',
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->getJson('/api/v1/external/leads');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    public function test_external_routes_full_suite(): void
    {
        $plainKey = 'jb_live_fullsuite12345678901234567';
        ApiKey::create([
            'name' => 'full-suite-key',
            'key' => hash('sha256', $plainKey),
            'is_active' => true,
        ]);

        $result = app(LeadIngestionService::class)->ingest([
            'full_name' => 'External Guy',
            'corporate_email' => 'ext@example.com',
            'company_name' => 'Ext Corp',
            'industry_classification' => 'Technology',
            'hq_location' => 'Berlin, Berlin, Germany',
            'title_tier' => 'VP-Level',
        ]);
        $lead = $result['lead'];

        // Show single lead
        $showRes = $this->withHeaders(['X-API-Key' => $plainKey])
            ->getJson("/api/v1/external/leads/{$lead->id}");
        $showRes->assertStatus(200)->assertJsonPath('data.full_name', 'External Guy');

        // Filter options
        $filterRes = $this->withHeaders(['X-API-Key' => $plainKey])
            ->getJson('/api/v1/external/leads/filters');
        $filterRes->assertStatus(200)->assertJsonStructure(['industries', 'countries']);

        // CSV Export
        $exportRes = $this->withHeaders(['X-API-Key' => $plainKey])
            ->get('/api/v1/external/leads/export/csv');
        $exportRes->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_allows_access_with_query_param(): void
    {
        $plainKey = 'jb_live_querykey12345678901234567890';
        ApiKey::create([
            'name' => 'query-key',
            'key' => hash('sha256', $plainKey),
            'rate_limit_per_minute' => 0, // unlimited
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/external/stats/summary?api_key={$plainKey}");

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['total_leads']]);
    }

    public function test_enforces_rate_limit(): void
    {
        $plainKey = 'jb_live_ratelimit12345678901234';
        ApiKey::create([
            'name' => 'rate-limit-key',
            'key' => hash('sha256', $plainKey),
            'rate_limit_per_minute' => 2, // Only 2 requests per minute allowed
            'is_active' => true,
        ]);

        // Request 1: should pass
        $this->getJson("/api/v1/external/stats/summary?api_key={$plainKey}")->assertStatus(200);

        // Request 2: should pass
        $this->getJson("/api/v1/external/stats/summary?api_key={$plainKey}")->assertStatus(200);

        // Request 3: should fail with 429
        $response = $this->getJson("/api/v1/external/stats/summary?api_key={$plainKey}");
        $response->assertStatus(429)
                 ->assertJsonFragment(['message' => 'Rate limit exceeded for this API key.']);
    }
}
