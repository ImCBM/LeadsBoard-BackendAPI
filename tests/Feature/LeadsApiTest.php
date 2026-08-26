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

        // Search term
        $res3 = $this->getJson('/api/v1/leads?search=Tech');
        $res3->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Lead Software', $res3->json('data.0.full_name'));
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
