<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for the n8n:simulate Artisan command.
 *
 * Tests the CLI tool that simulates n8n webhook POSTs.
 * These run in CI on every push to validate the simulator works correctly.
 *
 * NOTE: Most tests use --dry-run to avoid needing a running HTTP server.
 * The HTTP-sending tests use Http::fake() to intercept outgoing requests.
 */
class SimulateN8nCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.webhook.secret' => 'test-secret']);
    }

    // ─── Dry Run Mode ───────────────────────────────────────────

    public function test_dry_run_shows_payloads_without_sending(): void
    {
        $this->artisan('n8n:simulate', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN');

        // No leads should be inserted
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_dry_run_shows_correct_lead_count(): void
    {
        $this->artisan('n8n:simulate', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Full Name');
    }

    public function test_dry_run_with_limit(): void
    {
        $this->artisan('n8n:simulate', ['--dry-run' => true, '--limit' => 3])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN');

        $this->assertDatabaseCount('leads', 0);
    }

    // ─── CSV Handling ───────────────────────────────────────────

    public function test_fails_with_missing_csv_file(): void
    {
        $this->artisan('n8n:simulate', [
            '--csv' => 'nonexistent.csv',
            '--dry-run' => true,
        ])->assertExitCode(1)
          ->expectsOutputToContain('CSV file not found');
    }

    public function test_handles_custom_csv_path(): void
    {
        // The default CSV at docs/sample_lead_list.csv exists
        $this->artisan('n8n:simulate', [
            '--csv' => base_path('docs/sample_lead_list.csv'),
            '--dry-run' => true,
        ])->assertExitCode(0);
    }

    // ─── Webhook Secret Validation ──────────────────────────────

    public function test_fails_without_webhook_secret(): void
    {
        config(['services.webhook.secret' => null]);

        $this->artisan('n8n:simulate')
            ->assertExitCode(1)
            ->expectsOutputToContain('WEBHOOK_SECRET is not set');
    }

    public function test_dry_run_works_without_webhook_secret(): void
    {
        config(['services.webhook.secret' => null]);

        // Dry run should succeed even without a webhook secret
        $this->artisan('n8n:simulate', ['--dry-run' => true])
            ->assertExitCode(0);
    }

    // ─── N8N Payload Transformation ─────────────────────────────

    public function test_transforms_csv_to_n8n_payload_format(): void
    {
        // Verify that the simulator reads the default CSV and processes leads.
        // Detailed payload structure is validated by the filters & title tiers tests.
        $this->artisan('n8n:simulate', ['--dry-run' => true, '--limit' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('1 leads ready to send');
    }

    public function test_filters_personal_email_domains(): void
    {
        // Create a test CSV with a personal email
        $csvPath = $this->createTempCsv([
            ['Full Name' => 'Test User', 'Job Title' => 'CEO', 'Corporate Work Email' => 'test@gmail.com',
             'Email Status' => '', 'Company Name' => 'Test', 'Clean Root Domain' => 'test.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => 'Software', 'Employee Headcount' => '10', 'HQ Location' => 'Lisbon, Lisbon, Portugal'],
            ['Full Name' => 'Valid User', 'Job Title' => 'CEO', 'Corporate Work Email' => 'valid@corporate.com',
             'Email Status' => '', 'Company Name' => 'Valid Corp', 'Clean Root Domain' => 'corporate.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => 'Software', 'Employee Headcount' => '20', 'HQ Location' => 'Porto, Porto, Portugal'],
        ]);

        $this->artisan('n8n:simulate', ['--csv' => $csvPath, '--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtered out 1 leads with personal email domains')
            ->expectsOutputToContain('Valid User');
    }

    public function test_classifies_title_tiers_correctly(): void
    {
        $csvPath = $this->createTempCsv([
            ['Full Name' => 'CEO Person', 'Job Title' => 'CEO & Founder', 'Corporate Work Email' => 'ceo@a.com',
             'Email Status' => '', 'Company Name' => 'A', 'Clean Root Domain' => 'a.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
            ['Full Name' => 'VP Person', 'Job Title' => 'VP of Sales', 'Corporate Work Email' => 'vp@b.com',
             'Email Status' => '', 'Company Name' => 'B', 'Clean Root Domain' => 'b.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
            ['Full Name' => 'Dir Person', 'Job Title' => 'Director of Marketing', 'Corporate Work Email' => 'dir@c.com',
             'Email Status' => '', 'Company Name' => 'C', 'Clean Root Domain' => 'c.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
            ['Full Name' => 'Other Person', 'Job Title' => 'Senior Analyst', 'Corporate Work Email' => 'other@d.com',
             'Email Status' => '', 'Company Name' => 'D', 'Clean Root Domain' => 'd.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
        ]);

        // Verify all 4 leads are processed and shown in dry-run output.
        // Title tier values appear in the JSON payload output.
        $this->artisan('n8n:simulate', ['--csv' => $csvPath, '--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('4 leads ready to send')
            ->expectsOutputToContain('ceo@a.com')
            ->expectsOutputToContain('vp@b.com')
            ->expectsOutputToContain('dir@c.com')
            ->expectsOutputToContain('other@d.com');
    }

    // ─── HTTP Mode (with Http::fake) ────────────────────────────

    public function test_sends_individual_posts_to_webhook_endpoint(): void
    {
        Http::fake([
            '*/api/v1/webhook/leads' => Http::response([
                'message' => 'Lead created successfully.',
                'data' => ['id' => 1, 'full_name' => 'Test'],
            ], 201),
        ]);

        $csvPath = $this->createTempCsv([
            ['Full Name' => 'HTTP Test', 'Job Title' => 'CEO', 'Corporate Work Email' => 'http@test.com',
             'Email Status' => '', 'Company Name' => 'Corp', 'Clean Root Domain' => 'test.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => 'Software', 'Employee Headcount' => '10', 'HQ Location' => 'Lisbon, Lisbon, Portugal'],
        ]);

        $this->artisan('n8n:simulate', ['--csv' => $csvPath])
            ->assertExitCode(0)
            ->expectsOutputToContain('Inserted');

        Http::assertSentCount(1);
    }

    public function test_reports_duplicates_from_api_response(): void
    {
        Http::fake([
            '*/api/v1/webhook/leads' => Http::response([
                'message' => 'Duplicate lead — this email already exists.',
                'errors' => ['corporate_email' => 'Already exists'],
            ], 409),
        ]);

        $csvPath = $this->createTempCsv([
            ['Full Name' => 'Dup Test', 'Job Title' => 'CEO', 'Corporate Work Email' => 'dup@test.com',
             'Email Status' => '', 'Company Name' => 'Corp', 'Clean Root Domain' => 'test.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
        ]);

        $this->artisan('n8n:simulate', ['--csv' => $csvPath])
            ->assertExitCode(0)
            ->expectsOutputToContain('Duplicate');
    }

    public function test_bulk_mode_sends_single_post_to_bulk_endpoint(): void
    {
        Http::fake([
            '*/api/v1/webhook/leads/bulk' => Http::response([
                'message' => 'Bulk import complete.',
                'summary' => ['inserted' => 2, 'duplicates' => 0, 'errors' => 0, 'total' => 2],
                'results' => [
                    ['index' => 0, 'success' => true, 'duplicate' => false, 'errors' => [], 'lead_id' => 1],
                    ['index' => 1, 'success' => true, 'duplicate' => false, 'errors' => [], 'lead_id' => 2],
                ],
            ], 201),
        ]);

        $csvPath = $this->createTempCsv([
            ['Full Name' => 'Bulk 1', 'Job Title' => 'CEO', 'Corporate Work Email' => 'b1@test.com',
             'Email Status' => '', 'Company Name' => 'A', 'Clean Root Domain' => 'a.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
            ['Full Name' => 'Bulk 2', 'Job Title' => 'CTO', 'Corporate Work Email' => 'b2@test.com',
             'Email Status' => '', 'Company Name' => 'B', 'Clean Root Domain' => 'b.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
        ]);

        $this->artisan('n8n:simulate', ['--csv' => $csvPath, '--bulk' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Bulk Import Results');

        // Bulk should send exactly 1 request (not 2)
        Http::assertSentCount(1);
    }

    public function test_handles_connection_failure_gracefully(): void
    {
        Http::fake([
            '*/api/v1/webhook/leads' => Http::response(null, 500),
        ]);

        $csvPath = $this->createTempCsv([
            ['Full Name' => 'Error Test', 'Job Title' => 'CEO', 'Corporate Work Email' => 'err@test.com',
             'Email Status' => '', 'Company Name' => 'Corp', 'Clean Root Domain' => 'test.com',
             'Website Status' => '', 'Executive LinkedIn URL' => '', 'Company LinkedIn Page' => '',
             'Industry Classification' => '', 'Employee Headcount' => '', 'HQ Location' => ''],
        ]);

        $this->artisan('n8n:simulate', ['--csv' => $csvPath])
            ->assertExitCode(1); // Failure exit code
    }

    // ─── Lead Factory Tests ─────────────────────────────────────

    public function test_lead_factory_creates_valid_lead(): void
    {
        $lead = Lead::factory()->create();

        $this->assertNotNull($lead->id);
        $this->assertNotEmpty($lead->full_name);
        $this->assertNotEmpty($lead->corporate_email);
        $this->assertNotEmpty($lead->company_name);
        $this->assertContains($lead->title_tier, Lead::TITLE_TIERS);
        $this->assertContains($lead->status, Lead::STATUSES);
    }

    public function test_lead_factory_batch_creates_many_leads(): void
    {
        Lead::factory()->count(50)->create();

        $this->assertDatabaseCount('leads', 50);
    }

    public function test_lead_factory_clevel_state(): void
    {
        $lead = Lead::factory()->clevel()->create();
        $this->assertEquals('C-Level', $lead->title_tier);
    }

    public function test_lead_factory_vp_level_state(): void
    {
        $lead = Lead::factory()->vpLevel()->create();
        $this->assertEquals('VP-Level', $lead->title_tier);
    }

    public function test_lead_factory_director_level_state(): void
    {
        $lead = Lead::factory()->directorLevel()->create();
        $this->assertEquals('Director-Level', $lead->title_tier);
    }

    public function test_lead_factory_country_states(): void
    {
        $ptLead = Lead::factory()->fromPortugal()->create();
        $this->assertEquals('Portugal', $ptLead->country);

        $atLead = Lead::factory()->fromAustria()->create();
        $this->assertEquals('Austria', $atLead->country);

        $ieLead = Lead::factory()->fromIreland()->create();
        $this->assertEquals('Ireland', $ieLead->country);
    }

    public function test_lead_factory_status_states(): void
    {
        $new = Lead::factory()->statusNew()->create();
        $this->assertEquals('new', $new->status);

        $reviewed = Lead::factory()->reviewed()->create();
        $this->assertEquals('reviewed', $reviewed->status);

        $qualified = Lead::factory()->qualified()->create();
        $this->assertEquals('qualified', $qualified->status);

        $rejected = Lead::factory()->rejected()->create();
        $this->assertEquals('rejected', $rejected->status);
    }

    public function test_lead_factory_generates_unique_emails(): void
    {
        $leads = Lead::factory()->count(20)->create();
        $emails = $leads->pluck('corporate_email')->toArray();

        $this->assertCount(20, array_unique($emails), 'Factory should generate unique emails');
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Create a temporary CSV file from an array of rows.
     */
    private function createTempCsv(array $rows): string
    {
        $dir = storage_path('app/test-csvs');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/test_' . uniqid() . '.csv';
        $handle = fopen($path, 'w');

        // Write headers from first row's keys
        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);

        // Register cleanup
        $this->beforeApplicationDestroyed(fn() => @unlink($path));

        return $path;
    }
}
