<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.webhook.secret' => 'test-webhook-secret']);
    }

    public function test_rejects_webhook_request_without_token(): void
    {
        $payload = [
            'Full Name' => 'Frank Mortensen',
            'Job Title' => 'Deputy CEO',
            'Title Tier' => 'C-Level',
            'Corporate Work Email' => 'frank@example.com',
            'Company Name' => 'AL Sydbank',
        ];

        $response = $this->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Invalid or missing webhook token.']);
    }

    public function test_rejects_webhook_request_with_invalid_token(): void
    {
        $payload = [
            'Full Name' => 'Frank Mortensen',
            'Job Title' => 'Deputy CEO',
            'Title Tier' => 'C-Level',
            'Corporate Work Email' => 'frank@example.com',
            'Company Name' => 'AL Sydbank',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer wrong-secret',
        ])->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(401);
    }

    public function test_accepts_and_ingests_lead_with_valid_bearer_token(): void
    {
        $payload = [
            'Full Name' => 'Frank Mortensen',
            'Job Title' => 'Deputy CEO',
            'Title Tier' => 'C-Level',
            'Corporate Work Email' => 'frank@example.com',
            'Email Status' => '✅ Valid email',
            'Company Name' => 'AL Sydbank',
            'Clean Root Domain' => 'al-sydbank.dk',
            'Website Status' => 'HTTP 200 OK',
            'Executive LinkedIn URL' => 'https://www.linkedin.com/in/frank-mortensen',
            'Company LinkedIn Page' => 'https://www.linkedin.com/company/al-sydbank',
            'Industry Classification' => 'Banking / Financial Services',
            'Employee Headcount' => '53',
            'HQ Location' => 'Aabenraa, Southern Denmark, Denmark',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Lead created successfully.',
                     'data' => [
                         'full_name' => 'Frank Mortensen',
                         'job_title' => 'Deputy CEO',
                         'title_tier' => 'C-Level',
                         'corporate_email' => 'frank@example.com',
                         'company_name' => 'AL Sydbank',
                         'clean_root_domain' => 'al-sydbank.dk',
                         'industry_classification' => 'Banking / Financial Services',
                         'employee_headcount' => 53,
                         'country' => 'Denmark',
                         'ingestion_channel' => 'n8n',
                         'status' => 'new',
                     ],
                 ]);

        $this->assertDatabaseHas('leads', [
            'corporate_email' => 'frank@example.com',
        ]);
        $this->assertDatabaseHas('companies', [
            'name' => 'AL Sydbank',
            'employee_headcount' => 53,
        ]);
        $this->assertDatabaseHas('countries', [
            'name' => 'Denmark',
        ]);
    }

    public function test_rejects_duplicate_corporate_email(): void
    {
        app(LeadIngestionService::class)->ingest([
            'full_name' => 'Existing Lead',
            'corporate_email' => 'duplicate@example.com',
            'company_name' => 'Existing Corp',
            'title_tier' => 'C-Level',
        ]);

        $payload = [
            'Full Name' => 'New Guy Same Email',
            'Corporate Work Email' => 'duplicate@example.com',
            'Company Name' => 'Some Company',
        ];

        $response = $this->withHeaders([
            'X-Webhook-Token' => 'test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(409)
                 ->assertJson([
                     'message' => 'Duplicate lead — this email already exists.',
                 ]);
    }

    public function test_validates_required_fields_in_single_webhook(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads', [
            'Job Title' => 'Random Job',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['Full Name', 'Corporate Work Email', 'Company Name']);
    }

    public function test_bulk_lead_ingestion(): void
    {
        $payload = [
            'leads' => [
                [
                    'Full Name' => 'Lead One',
                    'Corporate Work Email' => 'lead1@example.com',
                    'Company Name' => 'Corp One',
                    'HQ Location' => 'Lisbon, Lisbon, Portugal',
                ],
                [
                    'Full Name' => 'Lead Two',
                    'Corporate Work Email' => 'lead2@example.com',
                    'Company Name' => 'Corp Two',
                    'HQ Location' => 'Dublin, Dublin, Ireland',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'summary' => [
                         'inserted' => 2,
                         'duplicates' => 0,
                         'errors' => 0,
                         'total' => 2,
                     ],
                 ]);

        $this->assertDatabaseHas('leads', ['corporate_email' => 'lead1@example.com']);
        $this->assertDatabaseHas('countries', ['name' => 'Portugal']);
        $this->assertDatabaseHas('leads', ['corporate_email' => 'lead2@example.com']);
        $this->assertDatabaseHas('countries', ['name' => 'Ireland']);
    }

    public function test_bulk_webhook_validation_errors(): void
    {
        // Empty payload
        $res1 = $this->withHeaders(['Authorization' => 'Bearer test-webhook-secret'])
            ->postJson('/api/v1/webhook/leads/bulk', []);
        $res1->assertStatus(422)->assertJsonValidationErrors(['leads']);

        // Empty leads array
        $res2 = $this->withHeaders(['Authorization' => 'Bearer test-webhook-secret'])
            ->postJson('/api/v1/webhook/leads/bulk', ['leads' => []]);
        $res2->assertStatus(422)->assertJsonValidationErrors(['leads']);

        // Missing fields inside item
        $res3 = $this->withHeaders(['Authorization' => 'Bearer test-webhook-secret'])
            ->postJson('/api/v1/webhook/leads/bulk', [
                'leads' => [
                    ['Job Title' => 'Missing details'],
                ],
            ]);
        $res3->assertStatus(422);
    }
}
