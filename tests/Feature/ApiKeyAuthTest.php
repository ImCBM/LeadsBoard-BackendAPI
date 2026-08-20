<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Lead;
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

        Lead::create([
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
}
