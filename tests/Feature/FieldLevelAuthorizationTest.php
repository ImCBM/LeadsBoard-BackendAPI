<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Lead;
use App\Models\Tier;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Field-Level Authorization & Response Masking.
 *
 * Verifies that:
 * - Free tier requests have restricted fields (contact_number) masked to null.
 * - Free tier responses include 'tier_restricted_fields' metadata.
 * - Pro and Enterprise tier requests receive full, unmasked contact numbers.
 * - Field restrictions are dynamically resolved from tier_field_restrictions table.
 *
 * TDD RED phase: Defines the contract for LeadResource response transformation.
 */
class FieldLevelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Lead $lead;
    private ApiKeyService $keyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->keyService = app(ApiKeyService::class);

        // Ensure a lead with a contact number exists
        $this->lead = Lead::first();
        if (!$this->lead) {
            $this->lead = Lead::create([
                'full_name' => 'John Doe',
                'job_title' => 'CEO',
                'title_tier' => 'C-Level',
                'corporate_email' => 'john.doe@example.com',
                'contact_number' => '+1-555-867-5309',
                'status' => 'new',
            ]);
        } else {
            $this->lead->update(['contact_number' => '+1-555-867-5309']);
        }
    }

    #[Test]
    public function free_tier_single_lead_masks_contact_number(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        $keyResult = $this->keyService->createKey([
            'name' => 'Free Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $freeUser);

        $response = $this->withHeader('X-API-Key', $keyResult['plain_text_key'])
            ->getJson("/api/v1/leads/{$this->lead->id}");

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        $this->assertNull($data['contact_number'], 'Contact number must be masked to null for free tier.');
        
        // Assert restricted fields metadata is reported
        $meta = $response->json('meta') ?? $response->json('meta.tier_restricted_fields') ?? ($data['meta'] ?? null);
        $restrictedFields = is_array($meta) && isset($meta['tier_restricted_fields'])
            ? $meta['tier_restricted_fields']
            : ($response->json('meta.tier_restricted_fields') ?? []);

        $this->assertContains('contact_number', $restrictedFields);
    }

    #[Test]
    public function free_tier_leads_list_masks_contact_number(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        $keyResult = $this->keyService->createKey([
            'name' => 'Free List Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $freeUser);

        $response = $this->withHeader('X-API-Key', $keyResult['plain_text_key'])
            ->getJson('/api/v1/leads');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertNull($item['contact_number'], 'Each lead in list must have contact_number masked to null for free tier.');
        }

        $restrictedFields = $response->json('meta.tier_restricted_fields') ?? [];
        $this->assertContains('contact_number', $restrictedFields);
    }

    #[Test]
    public function pro_tier_single_lead_returns_unmasked_contact_number(): void
    {
        $proUser = User::where('email', 'pro@leadsboard.local')->first();
        $keyResult = $this->keyService->createKey([
            'name' => 'Pro Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $proUser);

        $response = $this->withHeader('X-API-Key', $keyResult['plain_text_key'])
            ->getJson("/api/v1/leads/{$this->lead->id}");

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        $this->assertEquals('+1-555-867-5309', $data['contact_number']);

        $restrictedFields = $response->json('meta.tier_restricted_fields') ?? [];
        $this->assertNotContains('contact_number', $restrictedFields);
    }

    #[Test]
    public function enterprise_tier_returns_unmasked_contact_number(): void
    {
        $enterpriseUser = User::where('email', 'enterprise@leadsboard.local')->first();
        $keyResult = $this->keyService->createKey([
            'name' => 'Enterprise Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $enterpriseUser);

        $response = $this->withHeader('X-API-Key', $keyResult['plain_text_key'])
            ->getJson("/api/v1/leads/{$this->lead->id}");

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        $this->assertEquals('+1-555-867-5309', $data['contact_number']);
    }
}
