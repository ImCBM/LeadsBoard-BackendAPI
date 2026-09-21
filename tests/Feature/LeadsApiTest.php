<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LeadIngestionService $ingestionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->ingestionService = app(LeadIngestionService::class);
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_paginated_leads_and_filter(): void
    {
        $this->ingestionService->ingest([
            'full_name'               => 'Lead Finance',
            'corporate_email'         => 'fin@example.com',
            'company_name'            => 'Bank Corp',
            'industry_classification' => 'Financial Services',
            'title_tier'              => 'C-Level',
            'hq_location'             => 'Lisbon, Lisbon, Portugal',
            'status'                  => 'new',
        ]);

        $this->ingestionService->ingest([
            'full_name'               => 'Lead Software',
            'corporate_email'         => 'soft@example.com',
            'company_name'            => 'Tech Corp',
            'industry_classification' => 'Software',
            'title_tier'              => 'Director-Level',
            'hq_location'             => 'Vienna, Vienna, Austria',
            'status'                  => 'reviewed',
        ]);

        // General list
        $res1 = $this->getJson('/api/v1/leads');
        $res1->assertStatus(200)->assertJsonCount(2, 'data');

        // Filter by industry
        $res2 = $this->getJson('/api/v1/leads?industry=Financial Services');
        $res2->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Lead Finance', $res2->json('data.0.full_name'));

        // Filter by status
        $resStatus = $this->getJson('/api/v1/leads?status=reviewed');
        $resStatus->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Lead Software', $resStatus->json('data.0.full_name'));

        // Filter by title_tier
        $resTier = $this->getJson('/api/v1/leads?title_tier=C-Level');
        $resTier->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Lead Finance', $resTier->json('data.0.full_name'));

        // Filter by country
        $resCtry = $this->getJson('/api/v1/leads?country=Austria');
        $resCtry->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Lead Software', $resCtry->json('data.0.full_name'));

        // Search term
        $res3 = $this->getJson('/api/v1/leads?search=Tech');
        $res3->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Lead Software', $res3->json('data.0.full_name'));
    }

    public function test_can_sort_leads_relationally(): void
    {
        $this->ingestionService->ingest([
            'full_name'               => 'Alice Adams',
            'corporate_email'         => 'alice@zebra.com',
            'company_name'            => 'Zebra Corp',
            'industry_classification' => 'Automotive',
            'employee_headcount'      => 500,
            'hq_location'             => 'Berlin, Berlin, Germany',
        ]);

        $this->ingestionService->ingest([
            'full_name'               => 'Zoe Zimmerman',
            'corporate_email'         => 'zoe@alpha.com',
            'company_name'            => 'Alpha Corp',
            'industry_classification' => 'Software',
            'employee_headcount'      => 50,
            'hq_location'             => 'Vienna, Vienna, Austria',
        ]);

        // Sort by company_name ASC -> Alpha Corp first
        $resCompAsc = $this->getJson('/api/v1/leads?sort_by=company_name&sort_dir=asc');
        $resCompAsc->assertStatus(200);
        $this->assertEquals('Alpha Corp', $resCompAsc->json('data.0.company_name'));
        $this->assertEquals('Zebra Corp', $resCompAsc->json('data.1.company_name'));

        // Sort by company_name DESC -> Zebra Corp first
        $resCompDesc = $this->getJson('/api/v1/leads?sort_by=company_name&sort_dir=desc');
        $resCompDesc->assertStatus(200);
        $this->assertEquals('Zebra Corp', $resCompDesc->json('data.0.company_name'));

        // Sort by industry_classification ASC -> Automotive first
        $resIndAsc = $this->getJson('/api/v1/leads?sort_by=industry_classification&sort_dir=asc');
        $resIndAsc->assertStatus(200);
        $this->assertEquals('Automotive', $resIndAsc->json('data.0.industry_classification'));

        // Sort by country ASC -> Austria first
        $resCtryAsc = $this->getJson('/api/v1/leads?sort_by=country&sort_dir=asc');
        $resCtryAsc->assertStatus(200);
        $this->assertEquals('Austria', $resCtryAsc->json('data.0.country'));

        // Sort by employee_headcount DESC -> 500 first
        $resHCDec = $this->getJson('/api/v1/leads?sort_by=employee_headcount&sort_dir=desc');
        $resHCDec->assertStatus(200);
        $this->assertEquals(500, $resHCDec->json('data.0.employee_headcount'));

        // Sort by full_name ASC -> Alice first
        $resNameAsc = $this->getJson('/api/v1/leads?sort_by=full_name&sort_dir=asc');
        $resNameAsc->assertStatus(200);
        $this->assertEquals('Alice Adams', $resNameAsc->json('data.0.full_name'));
    }

    public function test_pagination_structure_and_limits(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->ingestionService->ingest([
                'full_name'       => "Person {$i}",
                'corporate_email' => "p{$i}@example.com",
                'company_name'    => "Company {$i}",
            ]);
        }

        $res = $this->getJson('/api/v1/leads?per_page=5&page=2');
        $res->assertStatus(200)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('total', 15)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(5, 'data');
    }

    public function test_can_create_lead_via_api_store(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'full_name'               => 'API Created',
            'corporate_email'         => 'api@created.com',
            'company_name'            => 'Created Tech',
            'industry_classification' => 'Healthtech',
            'title_tier'              => 'C-Level',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.full_name', 'API Created')
                 ->assertJsonPath('data.ingestion_channel', 'api');

        $this->assertDatabaseHas('leads', ['corporate_email' => 'api@created.com']);
        $this->assertDatabaseHas('companies', ['name' => 'Created Tech']);
    }

    public function test_api_store_rejects_duplicates_and_validates(): void
    {
        $this->postJson('/api/v1/leads', [
            'full_name'       => 'Original',
            'corporate_email' => 'unique@example.com',
            'company_name'    => 'Orig Corp',
        ])->assertStatus(201);

        // Duplicate
        $dupRes = $this->postJson('/api/v1/leads', [
            'full_name'       => 'Clone',
            'corporate_email' => 'unique@example.com',
            'company_name'    => 'Clone Corp',
        ]);
        $dupRes->assertStatus(409);

        // Missing required fields
        $invRes = $this->postJson('/api/v1/leads', [
            'full_name' => 'Missing Email & Company',
        ]);
        $invRes->assertStatus(422)
               ->assertJsonValidationErrors(['corporate_email', 'company_name']);
    }

    public function test_can_show_update_and_delete_lead(): void
    {
        $result = $this->ingestionService->ingest([
            'full_name'       => 'Old Name',
            'corporate_email' => 'old@example.com',
            'company_name'    => 'Old Corp',
            'title_tier'      => 'VP-Level',
            'status'          => 'new',
        ]);
        $lead = $result['lead'];

        // Show
        $showRes = $this->getJson("/api/v1/leads/{$lead->id}");
        $showRes->assertStatus(200)->assertJsonPath('data.full_name', 'Old Name');

        // Update
        $updateRes = $this->putJson("/api/v1/leads/{$lead->id}", [
            'full_name' => 'New Name',
            'status'    => 'qualified',
            'notes'     => 'Contacted on LinkedIn',
        ]);
        $updateRes->assertStatus(200)
                  ->assertJsonPath('data.full_name', 'New Name')
                  ->assertJsonPath('data.status', 'qualified');

        $this->assertDatabaseHas('leads', [
            'id'        => $lead->id,
            'full_name' => 'New Name',
            'status'    => 'qualified',
            'notes'     => 'Contacted on LinkedIn',
        ]);

        // Delete
        $deleteRes = $this->deleteJson("/api/v1/leads/{$lead->id}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_lead_endpoints_return_404_for_missing_ids(): void
    {
        $this->getJson('/api/v1/leads/999999')->assertStatus(404);
        $this->putJson('/api/v1/leads/999999', ['full_name' => 'Ghost'])->assertStatus(404);
        $this->deleteJson('/api/v1/leads/999999')->assertStatus(404);
    }

    public function test_lead_update_validates_enum_fields(): void
    {
        $result = $this->ingestionService->ingest([
            'full_name'       => 'Validation Test',
            'corporate_email' => 'val@example.com',
            'company_name'    => 'Val Corp',
        ]);
        $lead = $result['lead'];

        $res = $this->putJson("/api/v1/leads/{$lead->id}", [
            'status' => 'invalid-status-value',
            'title_tier' => 'invalid-tier',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'title_tier']);
    }

    public function test_can_export_csv(): void
    {
        $this->ingestionService->ingest([
            'full_name'       => 'Exportable Lead',
            'corporate_email' => 'export@example.com',
            'company_name'    => 'Export Corp',
            'title_tier'      => 'C-Level',
        ]);

        $response = $this->get('/api/v1/leads/export/csv');

        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_can_fetch_filter_options(): void
    {
        $this->ingestionService->ingest([
            'full_name'               => 'Options Lead',
            'corporate_email'         => 'options@example.com',
            'company_name'            => 'Options Corp',
            'industry_classification' => 'Aerospace',
            'hq_location'             => 'Lisbon, Lisbon, Portugal',
        ]);

        $response = $this->getJson('/api/v1/leads/filters');

        $response->assertStatus(200)
                 ->assertJsonStructure(['industries', 'title_tiers', 'statuses', 'countries', 'channels'])
                 ->assertJsonFragment(['Aerospace'])
                 ->assertJsonFragment(['Portugal']);
    }
}
