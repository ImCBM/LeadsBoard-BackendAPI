<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Location;
use App\Services\LeadIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for LeadIngestionService.
 *
 * Validates the core 3NF ingestion and normalization pipeline.
 */
class LeadIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadIngestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeadIngestionService();
    }

    // ─── Field Mapping & 3NF Ingestion ─────────────────────────

    public function test_maps_n8n_style_keys_to_snake_case(): void
    {
        $result = $this->service->ingest([
            'Full Name'               => 'Jane Doe',
            'Corporate Work Email'    => 'jane@example.com',
            'Company Name'            => 'Acme Corp',
            'Job Title'               => 'CEO',
            'Title Tier'              => 'C-Level',
            'Industry Classification' => 'Software',
            'HQ Location'             => 'Lisbon, Lisbon, Portugal',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Jane Doe', $result['lead']->full_name);
        $this->assertEquals('jane@example.com', $result['lead']->corporate_email);
        $this->assertEquals('Acme Corp', $result['lead']->company_name);
        $this->assertEquals('Software', $result['lead']->industry_classification);
        $this->assertEquals('Portugal', $result['lead']->country);

        // Verify 3NF relational records in separate tables
        $this->assertDatabaseHas('leads', ['corporate_email' => 'jane@example.com']);
        $this->assertDatabaseHas('companies', ['name' => 'Acme Corp']);
        $this->assertDatabaseHas('industries', ['name' => 'Software']);
        $this->assertDatabaseHas('countries', ['name' => 'Portugal']);
        $this->assertDatabaseHas('locations', ['raw_location' => 'Lisbon, Lisbon, Portugal']);
    }

    public function test_maps_snake_case_keys_directly(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'john smith',
            'corporate_email' => 'john@testcorp.com',
            'company_name'    => 'Test Corp',
        ]);

        $this->assertTrue($result['success']);
        // trim-only: original casing preserved
        $this->assertEquals('john smith', $result['lead']->full_name);
        $this->assertEquals('john@testcorp.com', $result['lead']->corporate_email);
        $this->assertDatabaseHas('companies', ['name' => 'Test Corp']);
    }

    public function test_ignores_unknown_fields_silently(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Alice',
            'corporate_email' => 'alice@corp.com',
            'company_name'    => 'Corp',
            'random_field'    => 'should be ignored',
            'another_one'     => 12345,
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('leads', ['corporate_email' => 'alice@corp.com']);
    }

    // ─── Normalization ──────────────────────────────────────────

    public function test_preserves_full_name_casing(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'frank MORTENSEN',
            'corporate_email' => 'frank@example.com',
            'company_name'    => 'Test',
        ]);

        $this->assertTrue($result['success']);
        // trim-only: original casing preserved (Str::title destroyed McDonald -> Mcdonald)
        $this->assertEquals('frank MORTENSEN', $result['lead']->full_name);
    }

    public function test_trims_whitespace_from_full_name(): void
    {
        $result = $this->service->ingest([
            'full_name'       => '  Frank Mortensen  ',
            'corporate_email' => 'frank2@example.com',
            'company_name'    => 'Test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Frank Mortensen', $result['lead']->full_name);
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Test User',
            'corporate_email' => 'USER@EXAMPLE.COM',
            'company_name'    => 'Test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('user@example.com', $result['lead']->corporate_email);
    }

    public function test_strips_protocol_and_www_from_domain(): void
    {
        $result = $this->service->ingest([
            'full_name'         => 'Test',
            'corporate_email'   => 'test@domain.com',
            'company_name'      => 'Test',
            'clean_root_domain' => 'https://www.example.com/',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('example.com', $result['lead']->clean_root_domain);
        $this->assertDatabaseHas('companies', ['clean_root_domain' => 'example.com']);
    }

    public function test_parses_employee_headcount_from_string(): void
    {
        $result = $this->service->ingest([
            'Full Name'            => 'Test',
            'Corporate Work Email' => 'hc@test.com',
            'Company Name'         => 'Test',
            'Employee Headcount'   => '53',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(53, $result['lead']->employee_headcount);
        $this->assertDatabaseHas('companies', ['employee_headcount' => 53]);
    }

    public function test_handles_empty_employee_headcount(): void
    {
        $result = $this->service->ingest([
            'Full Name'            => 'Test',
            'Corporate Work Email' => 'empty-hc@test.com',
            'Company Name'         => 'Test',
            'Employee Headcount'   => '',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNull($result['lead']->employee_headcount);
    }

    public function test_nullifies_invalid_linkedin_urls(): void
    {
        $result = $this->service->ingest([
            'full_name'              => 'Test',
            'corporate_email'        => 'linkedin@test.com',
            'company_name'           => 'Test',
            'executive_linkedin_url' => 'not-a-valid-url',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNull($result['lead']->executive_linkedin_url);
    }

    public function test_converts_empty_strings_to_null_for_nullable_fields(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Test',
            'corporate_email' => 'nullable@test.com',
            'company_name'    => 'Test',
            'job_title'       => '',
            'hq_location'     => '',
            'notes'           => '',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNull($result['lead']->job_title);
        $this->assertNull($result['lead']->hq_location);
        $this->assertNull($result['lead']->notes);
    }

    // ─── Title Tier Normalization ────────────────────────────────

    public function test_normalizes_known_title_tiers(): void
    {
        foreach (['C-Level', 'VP-Level', 'Director-Level', 'Other'] as $tier) {
            $result = $this->service->ingest([
                'full_name'       => "Tier Test {$tier}",
                'corporate_email' => strtolower(str_replace('-', '', $tier)) . '@test.com',
                'company_name'    => 'Test',
                'title_tier'      => $tier,
            ]);

            $this->assertTrue($result['success'], "Failed for tier: {$tier}");
            $this->assertEquals($tier, $result['lead']->title_tier);
        }
    }

    public function test_defaults_empty_title_tier_to_other(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'No Tier',
            'corporate_email' => 'notier@test.com',
            'company_name'    => 'Test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Other', $result['lead']->title_tier);
    }

    public function test_fuzzy_matches_title_tier_keywords(): void
    {
        $result1 = $this->service->ingest([
            'full_name' => 'Fuzzy1', 'corporate_email' => 'fuzzy1@test.com',
            'company_name' => 'Test', 'title_tier' => 'some c-level thing',
        ]);
        $this->assertEquals('C-Level', $result1['lead']->title_tier);

        $result2 = $this->service->ingest([
            'full_name' => 'Fuzzy2', 'corporate_email' => 'fuzzy2@test.com',
            'company_name' => 'Test', 'title_tier' => 'senior vp role',
        ]);
        $this->assertEquals('VP-Level', $result2['lead']->title_tier);

        $result3 = $this->service->ingest([
            'full_name' => 'Fuzzy3', 'corporate_email' => 'fuzzy3@test.com',
            'company_name' => 'Test', 'title_tier' => 'a director position',
        ]);
        $this->assertEquals('Director-Level', $result3['lead']->title_tier);
    }

    // ─── Deduplication ──────────────────────────────────────────

    public function test_rejects_duplicate_by_corporate_email(): void
    {
        $result1 = $this->service->ingest([
            'full_name'       => 'First',
            'corporate_email' => 'duplicate@test.com',
            'company_name'    => 'Corp A',
        ]);
        $this->assertTrue($result1['success']);

        $result2 = $this->service->ingest([
            'full_name'       => 'Second',
            'corporate_email' => 'duplicate@test.com',
            'company_name'    => 'Corp B',
        ]);
        $this->assertFalse($result2['success']);
        $this->assertTrue($result2['duplicate']);
        $this->assertNull($result2['lead']);
    }

    public function test_dedup_is_case_insensitive(): void
    {
        $this->service->ingest([
            'full_name'       => 'First',
            'corporate_email' => 'CaseTest@Example.COM',
            'company_name'    => 'Corp',
        ]);

        $result = $this->service->ingest([
            'full_name'       => 'Second',
            'corporate_email' => 'casetest@example.com',
            'company_name'    => 'Corp',
        ]);

        $this->assertTrue($result['duplicate']);
    }

    public function test_allows_different_emails_for_same_company(): void
    {
        $r1 = $this->service->ingest([
            'full_name'         => 'Person A',
            'corporate_email'   => 'a@samecompany.com',
            'company_name'      => 'Same Company',
            'clean_root_domain' => 'samecompany.com',
        ]);
        $r2 = $this->service->ingest([
            'full_name'         => 'Person B',
            'corporate_email'   => 'b@samecompany.com',
            'company_name'      => 'Same Company',
            'clean_root_domain' => 'samecompany.com',
        ]);

        $this->assertTrue($r1['success']);
        $this->assertTrue($r2['success']);

        // Both leads should point to the SAME company record in 3NF
        $this->assertEquals($r1['lead']->company_id, $r2['lead']->company_id);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('leads', 2);
    }

    // ─── Country Extraction ─────────────────────────────────────

    public function test_extracts_country_from_hq_location(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Geo Test',
            'corporate_email' => 'geo@test.com',
            'company_name'    => 'Test',
            'hq_location'     => 'Lisbon, Lisbon, Portugal',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Portugal', $result['lead']->country);
        $this->assertDatabaseHas('countries', ['name' => 'Portugal']);
    }

    public function test_extracts_country_from_three_part_location(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Vienna Test',
            'corporate_email' => 'vienna@test.com',
            'company_name'    => 'Test',
            'hq_location'     => 'Vienna, Vienna, Austria',
        ]);

        $this->assertEquals('Austria', $result['lead']->country);
        $this->assertDatabaseHas('countries', ['name' => 'Austria']);
    }

    public function test_does_not_extract_country_from_single_part_location(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Single Part',
            'corporate_email' => 'single@test.com',
            'company_name'    => 'Test',
            'hq_location'     => 'Unknown',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNull($result['lead']->country);
    }

    // ─── Metadata ───────────────────────────────────────────────

    public function test_sets_ingestion_channel(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Channel Test',
            'corporate_email' => 'channel@test.com',
            'company_name'    => 'Test',
        ], 'csv_import');

        $this->assertTrue($result['success']);
        $this->assertEquals('csv_import', $result['lead']->ingestion_channel);
    }

    public function test_default_channel_is_n8n(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Default',
            'corporate_email' => 'default@test.com',
            'company_name'    => 'Test',
        ]);

        $this->assertEquals('n8n', $result['lead']->ingestion_channel);
    }

    public function test_new_leads_have_status_new(): void
    {
        $result = $this->service->ingest([
            'full_name'       => 'Status Test',
            'corporate_email' => 'status@test.com',
            'company_name'    => 'Test',
        ]);

        $this->assertEquals('new', $result['lead']->status);
    }

    // ─── Bulk Ingestion ─────────────────────────────────────────

    public function test_bulk_ingest_returns_correct_counts(): void
    {
        $items = [
            ['full_name' => 'Bulk 1', 'corporate_email' => 'bulk1@test.com', 'company_name' => 'A'],
            ['full_name' => 'Bulk 2', 'corporate_email' => 'bulk2@test.com', 'company_name' => 'B'],
            ['full_name' => 'Bulk 3', 'corporate_email' => 'bulk3@test.com', 'company_name' => 'C'],
        ];

        $result = $this->service->bulkIngest($items);

        $this->assertEquals(3, $result['inserted']);
        $this->assertEquals(0, $result['duplicates']);
        $this->assertEquals(0, $result['errors']);
        $this->assertCount(3, $result['results']);
    }

    public function test_bulk_ingest_counts_duplicates(): void
    {
        $this->service->ingest([
            'full_name'       => 'Existing',
            'corporate_email' => 'existing@test.com',
            'company_name'    => 'Corp',
        ]);

        $items = [
            ['full_name' => 'New',    'corporate_email' => 'new@test.com',      'company_name' => 'A'],
            ['full_name' => 'Dup',    'corporate_email' => 'existing@test.com', 'company_name' => 'B'],
        ];

        $result = $this->service->bulkIngest($items);

        $this->assertEquals(1, $result['inserted']);
        $this->assertEquals(1, $result['duplicates']);
    }

    public function test_bulk_ingest_handles_intra_batch_duplicates(): void
    {
        $items = [
            ['full_name' => 'First',  'corporate_email' => 'same@test.com', 'company_name' => 'A'],
            ['full_name' => 'Second', 'corporate_email' => 'same@test.com', 'company_name' => 'B'],
        ];

        $result = $this->service->bulkIngest($items);

        $this->assertEquals(1, $result['inserted']);
        $this->assertEquals(1, $result['duplicates']);
    }
}
