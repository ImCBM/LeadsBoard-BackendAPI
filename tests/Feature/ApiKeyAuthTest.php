<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Lead;
use App\Services\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    public function test_can_create_lead_via_external_api_key_endpoint(): void
    {
        $plainKey = 'jb_live_externalpost123456789012';

        ApiKey::create([
            'name' => 'external-post-test',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads', [
            'full_name' => 'External API Lead',
            'corporate_email' => 'externalpost@example.com',
            'company_name' => 'External Test Corp',
            'title_tier' => 'C-Level',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'message' => 'Lead created successfully.',
            ]);

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'externalpost@example.com',
        ]);
    }

    public function test_rejects_external_lead_creation_without_api_key(): void
    {
        $response = $this->postJson('/api/v1/external/leads', [
            'full_name' => 'Unauthenticated Lead',
            'corporate_email' => 'unauthenticated@example.com',
            'company_name' => 'Example Corp',
            'title_tier' => 'C-Level',
        ]);

        $response->assertStatus(401);

        $this->assertDatabaseMissing('leads', [
            'corporate_email' => 'unauthenticated@example.com',
        ]);
    }

    public function test_rejects_external_lead_creation_with_invalid_api_key(): void
    {
        $invalidKey = '12345678910';

        $response = $this->withHeaders([
            'X-API-Key' => $invalidKey,
        ])->postJson('/api/v1/external/leads', [
            'full_name' => 'Unauthenticated Lead',
            'corporate_email' => 'unauthenticated@example.com',
            'company_name' => 'Example Corp',
            'title_tier' => 'C-Level',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('leads', [
            'corporate_email' => 'unauthenticated@example.com',
        ]);
    }

    public function test_rejects_duplicate_lead_creation_via_external_api(): void
    {
        $plainKey = 'jb_live_duplicatekey123456789012';

        ApiKey::create([
            'name' => 'duplicate-test',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $response1 = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads', [
            'full_name' => 'Duplicate Lead',
            'corporate_email' => 'duplicate@example.com',
            'company_name' => 'Example Corp',
            'title_tier' => 'C-Level',
        ]);

        $response2 = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads', [
            'full_name' => 'Duplicate Lead',
            'corporate_email' => 'duplicate@example.com',
            'company_name' => 'Example Corp',
            'title_tier' => 'C-Level',
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(409);

        $this->assertSame(
            1,
            DB::table('leads')
                ->where('corporate_email', 'duplicate@example.com')
                ->count()
        );
    }

    public function test_rejects_invalid_lead_data_via_external_api(): void
    {
        $plainKey = 'jb_live_duplicatekey123456789012';

        ApiKey::create([
            'name' => 'invalid-data-test',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads', [
            'full_name' => 'Invalid Data Lead',
            'corporate_email' => '',
            'company_name' => 'Example Corp',
            'title_tier' => 'C-Level',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['corporate_email']);
    }

    public function test_can_create_leads_in_bulk_via_external_api(): void
    {
        $plainKey = 'jb_live_duplicatekey123456789012';

        ApiKey::create([
            'name' => 'bulk-ingestion-test',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads/bulk', [
            'leads' => [
                [
                    'full_name' => 'Jack Daniels',
                    'corporate_email' => 'j.daniels@example.com',
                    'company_name' => 'Example Corp',
                    'title_tier' => 'C-Level',
                ],
                [
                    'full_name' => 'Jack Sparrow',
                    'corporate_email' => 'j.sparrow@example.com',
                    'company_name' => 'Example Corp',
                    'title_tier' => 'A-Level',
                ],
            ]
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('summary.inserted', 2);
        $response->assertJsonPath('summary.duplicates', 0);
        $response->assertJsonPath('summary.errors', 0);
        $response->assertJsonPath('summary.total', 2);

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'j.daniels@example.com',
        ]);

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'j.sparrow@example.com',
        ]);
    }

    public function test_bulk_api_handles_duplicate_leads(): void
    {
        $plainKey = 'jb_live_duplicatekey123456789012';

        ApiKey::create([
            'name' => 'bulk-ingestion-test',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads/bulk', [
            'leads' => [
                [
                    'full_name' => 'Jack Daniels',
                    'corporate_email' => 'duplicate@example.com',
                    'company_name' => 'Example Corp',
                    'title_tier' => 'C-Level',
                ],
                [
                    'full_name' => 'Jack Sparrow',
                    'corporate_email' => 'duplicate@example.com',
                    'company_name' => 'Example Corp',
                    'title_tier' => 'A-Level',
                ],
            ]
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('summary.inserted', 1);
        $response->assertJsonPath('summary.duplicates', 1);
        $response->assertJsonPath('summary.errors', 0);
    }


    public function test_bulk_api_skips_leads_that_already_exist(): void
    {

        $plainKey = 'jb_live_duplicatekey123456789012';

        ApiKey::create([
            'name' => 'bulk-ingestion-test',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => 'jb_live_',
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        app(LeadIngestionService::class)->ingest([
            'full_name' => 'Existing Lead',
            'corporate_email' => 'existing@example.com',
            'company_name' => 'Existing Corp',
            'title_tier' => 'C-Level',
        ]);

        $payload = [
            'leads' => [
                [
                    'Full Name' => 'Duplicate Lead',
                    'Corporate Work Email' => 'existing@example.com',
                    'Company Name' => 'Another Corp',
                ],
                [
                    'Full Name' => 'New Lead',
                    'Corporate Work Email' => 'new@example.com',
                    'Company Name' => 'New Corp',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => $plainKey,
        ])->postJson('/api/v1/external/leads/bulk', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'summary' => [
                    'inserted' => 1,
                    'duplicates' => 1,
                    'errors' => 0,
                    'total' => 2,
                ],
            ]);

        $this->assertSame(
            1,
            Lead::where('corporate_email', 'existing@example.com')->count()
        );

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'new@example.com',
            'full_name' => 'New Lead',
        ]);
    }
}
