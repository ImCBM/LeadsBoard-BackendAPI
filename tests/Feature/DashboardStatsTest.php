<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_stats_endpoints(): void
    {
        $ingestionService = app(LeadIngestionService::class);

        $ingestionService->ingest([
            'full_name'               => 'Lead One',
            'corporate_email'         => 'one@example.com',
            'company_name'            => 'Alpha',
            'industry_classification' => 'Real Estate',
            'title_tier'              => 'C-Level',
            'hq_location'             => 'Lisbon, Lisbon, Portugal',
            'status'                  => 'new',
        ]);

        $ingestionService->ingest([
            'full_name'               => 'Lead Two',
            'corporate_email'         => 'two@example.com',
            'company_name'            => 'Beta',
            'industry_classification' => 'Real Estate',
            'title_tier'              => 'Director-Level',
            'hq_location'             => 'Vienna, Vienna, Austria',
            'status'                  => 'qualified',
        ]);

        // Summary
        $resSum = $this->getJson('/api/v1/stats/summary');
        $resSum->assertStatus(200)
               ->assertJsonPath('data.total_leads', 2)
               ->assertJsonPath('data.status_counts.new', 1)
               ->assertJsonPath('data.status_counts.qualified', 1);

        // By Industry
        $resInd = $this->getJson('/api/v1/stats/by-industry');
        $resInd->assertStatus(200)
               ->assertJsonFragment(['industry_classification' => 'Real Estate', 'count' => 2]);

        // By Title Tier
        $resTier = $this->getJson('/api/v1/stats/by-title-tier');
        $resTier->assertStatus(200)
                ->assertJsonFragment(['title_tier' => 'C-Level', 'count' => 1]);

        // By Status
        $resSt = $this->getJson('/api/v1/stats/by-status');
        $resSt->assertStatus(200)
              ->assertJsonFragment(['status' => 'new', 'count' => 1]);

        // By Country
        $resCtry = $this->getJson('/api/v1/stats/by-country');
        $resCtry->assertStatus(200)
                ->assertJsonFragment(['country' => 'Portugal', 'count' => 1]);

        // Timeline
        $resTime = $this->getJson('/api/v1/stats/timeline?days=7');
        $resTime->assertStatus(200)
                ->assertJsonStructure(['data', 'range']);
    }
}
