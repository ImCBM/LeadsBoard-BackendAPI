<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteAndTagsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test-webhook-secret']);

        // Create admin and sanctum token
        $this->admin = User::factory()->create([
            'email' => 'admin@leadsboard.local',
            'role'  => 'admin',
        ]);
        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    public function test_ingesting_lead_with_tags_via_webhook(): void
    {
        $payload = [
            'Full Name'            => 'Alice Morgan',
            'Job Title'            => 'CEO',
            'Corporate Work Email' => 'alice@morgan-tech.com',
            'Company Name'         => 'Morgan Tech',
            'Tags'                 => ['test', 'vip'],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads', $payload);

        $response->assertStatus(201);

        $lead = Lead::where('corporate_email', 'alice@morgan-tech.com')->first();
        $this->assertNotNull($lead);
        $this->assertCount(2, $lead->tags);
        $this->assertContains('Test', $lead->tag_names);
        $this->assertContains('VIP', $lead->tag_names);
    }

    public function test_bulk_ingest_with_tags_via_webhook(): void
    {
        $payload = [
            'leads' => [
                [
                    'Full Name'            => 'Bob Vance',
                    'Corporate Work Email' => 'bob@vancerefrigeration.com',
                    'Company Name'         => 'Vance Refrigeration',
                    'Tags'                 => 'demo, sample',
                ],
                [
                    'Full Name'            => 'Phyllis Lapin',
                    'Corporate Work Email' => 'phyllis@vancerefrigeration.com',
                    'Company Name'         => 'Vance Refrigeration',
                    'tags'                 => ['test'],
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk', $payload);

        $response->assertStatus(201);

        $lead1 = Lead::where('corporate_email', 'bob@vancerefrigeration.com')->first();
        $lead2 = Lead::where('corporate_email', 'phyllis@vancerefrigeration.com')->first();

        $this->assertNotNull($lead1);
        $this->assertNotNull($lead2);
        $this->assertContains('Demo', $lead1->tag_names);
        $this->assertContains('Test', $lead2->tag_names);
    }

    public function test_filtering_leads_by_tag(): void
    {
        $tag = Tag::findOrCreateByName('Test', Tag::TYPE_SYSTEM);

        $lead1 = Lead::create([
            'full_name'       => 'Lead One',
            'corporate_email' => 'lead1@example.com',
            'status'          => 'new',
        ]);
        $lead1->attachTags([$tag]);

        $lead2 = Lead::create([
            'full_name'       => 'Lead Two',
            'corporate_email' => 'lead2@example.com',
            'status'          => 'new',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/leads?tag=test');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('lead1@example.com', $response->json('data.0.corporate_email'));
    }

    public function test_webhook_bulk_delete_by_ids(): void
    {
        $lead1 = Lead::create(['full_name' => 'L1', 'corporate_email' => 'l1@corp.com']);
        $lead2 = Lead::create(['full_name' => 'L2', 'corporate_email' => 'l2@corp.com']);
        $lead3 = Lead::create(['full_name' => 'L3', 'corporate_email' => 'l3@corp.com']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk-delete', [
            'lead_ids' => [$lead1->id, $lead2->id],
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'deleted_count' => 2,
                     'deleted_ids'   => [$lead1->id, $lead2->id],
                 ]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_webhook_bulk_delete_by_tag(): void
    {
        $testTag = Tag::findOrCreateByName('Test', Tag::TYPE_SYSTEM);
        $vipTag  = Tag::findOrCreateByName('VIP', Tag::TYPE_PUBLIC);

        $lead1 = Lead::create(['full_name' => 'Test Lead 1', 'corporate_email' => 'test1@test.com']);
        $lead1->attachTags([$testTag]);

        $lead2 = Lead::create(['full_name' => 'Test Lead 2', 'corporate_email' => 'test2@test.com']);
        $lead2->attachTags([$testTag, $vipTag]);

        $lead3 = Lead::create(['full_name' => 'Prod Lead', 'corporate_email' => 'prod@real.com']);
        $lead3->attachTags([$vipTag]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk-delete', [
            'tag' => 'test',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'deleted_count' => 2,
                 ]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_authenticated_bulk_delete_by_emails(): void
    {
        $lead1 = Lead::create(['full_name' => 'User 1', 'corporate_email' => 'user1@target.com']);
        $lead2 = Lead::create(['full_name' => 'User 2', 'corporate_email' => 'user2@target.com']);
        $lead3 = Lead::create(['full_name' => 'User 3', 'corporate_email' => 'keep@target.com']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/leads/bulk-delete', [
            'emails' => ['user1@target.com', 'user2@target.com'],
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'deleted_count' => 2,
                 ]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_bulk_delete_validation_requires_selector_or_confirm(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/leads/bulk-delete', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['criteria']);
    }

    public function test_bulk_tag_leads(): void
    {
        $lead1 = Lead::create(['full_name' => 'L1', 'corporate_email' => 'l1@test.com']);
        $lead2 = Lead::create(['full_name' => 'L2', 'corporate_email' => 'l2@test.com']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/leads/bulk-tag', [
            'lead_ids' => [$lead1->id, $lead2->id],
            'add_tags' => ['high-priority', 'partner'],
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'updated_count' => 2,
                 ]);

        $lead1->refresh();
        $this->assertContains('High Priority', $lead1->tag_names);
        $this->assertContains('Partner', $lead1->tag_names);
    }

    public function test_tag_crud_api(): void
    {
        // 1. Create tag
        $createRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/tags', [
            'name'        => 'Custom Segment',
            'type'        => 'public',
            'color'       => '#10b981',
            'description' => 'Custom prospect segment',
        ]);

        $createRes->assertStatus(201)
                  ->assertJsonPath('data.name', 'Custom Segment')
                  ->assertJsonPath('data.slug', 'custom-segment');

        $tagId = $createRes->json('data.id');

        // 2. List tags
        $listRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/tags');

        $listRes->assertStatus(200);

        // 3. Delete tag
        $deleteRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/v1/tags/{$tagId}");

        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('tags', ['id' => $tagId]);
    }

    public function test_cli_retroactive_tag_command(): void
    {
        Lead::create(['full_name' => 'CLI 1', 'corporate_email' => 'cli1@n8n.test', 'ingestion_channel' => 'n8n']);
        Lead::create(['full_name' => 'CLI 2', 'corporate_email' => 'cli2@n8n.test', 'ingestion_channel' => 'n8n']);
        Lead::create(['full_name' => 'CLI 3', 'corporate_email' => 'cli3@api.test', 'ingestion_channel' => 'api']);

        $this->artisan('leads:tag', [
            '--channel' => 'n8n',
            '--tag'     => 'test',
        ])->assertSuccessful();

        $taggedLeads = Lead::byTag('test')->get();
        $this->assertCount(2, $taggedLeads);
    }

    public function test_cli_cleanup_test_command(): void
    {
        $testTag = Tag::findOrCreateByName('Test', Tag::TYPE_SYSTEM);
        $lead1 = Lead::create(['full_name' => 'Cleanup 1', 'corporate_email' => 'c1@test.com']);
        $lead1->attachTags([$testTag]);

        $lead2 = Lead::create(['full_name' => 'Keep Lead', 'corporate_email' => 'keep@prod.com']);

        $this->artisan('leads:cleanup-test', [
            '--tag'   => 'test',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead2->id]);
    }

    public function test_bulk_delete_by_email_domain(): void
    {
        $lead1 = Lead::create(['full_name' => 'Alice', 'corporate_email' => 'alice@demodomain.com']);
        $lead2 = Lead::create(['full_name' => 'Bob', 'corporate_email' => 'bob@demodomain.com']);
        $lead3 = Lead::create(['full_name' => 'Charlie', 'corporate_email' => 'charlie@demodomain.net']);
        $lead4 = Lead::create(['full_name' => 'Dave', 'corporate_email' => 'dave@otherdomain.com']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk-delete', [
            'email_domain' => 'demodomain.com',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'deleted_count' => 2,
                 ]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]); // .net protected!
        $this->assertDatabaseHas('leads', ['id' => $lead4->id]);
    }

    public function test_bulk_delete_by_email_pattern(): void
    {
        $lead1 = Lead::create(['full_name' => 'Alice', 'corporate_email' => 'alice@demodomain.com']);
        $lead2 = Lead::create(['full_name' => 'Bob', 'corporate_email' => 'bob@demodomain.net']);
        $lead3 = Lead::create(['full_name' => 'Charlie', 'corporate_email' => 'charlie@unrelated.com']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk-delete', [
            'email_pattern' => '%@demodomain.%',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'deleted_count' => 2,
                 ]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_bulk_delete_by_mixed_emails_array_with_domain_prefix(): void
    {
        $lead1 = Lead::create(['full_name' => 'User 1', 'corporate_email' => 'lead1@wildcard.com']);
        $lead2 = Lead::create(['full_name' => 'User 2', 'corporate_email' => 'specific@other.com']);
        $lead3 = Lead::create(['full_name' => 'User 3', 'corporate_email' => 'keep@other.com']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/leads/bulk-delete', [
            'emails' => ['@wildcard.com', 'specific@other.com'],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['deleted_count' => 2]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_bulk_delete_by_id_ranges(): void
    {
        $lead1 = Lead::create(['full_name' => 'L1', 'corporate_email' => 'l1@corp.com']);
        $lead2 = Lead::create(['full_name' => 'L2', 'corporate_email' => 'l2@corp.com']);
        $lead3 = Lead::create(['full_name' => 'L3', 'corporate_email' => 'l3@corp.com']);
        $lead4 = Lead::create(['full_name' => 'L4', 'corporate_email' => 'l4@corp.com']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/leads/bulk-delete', [
            'id_ranges' => ["{$lead1->id}-{$lead2->id}", "{$lead4->id}-{$lead4->id}"],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['deleted_count' => 3]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]); // lead3 preserved!
        $this->assertDatabaseMissing('leads', ['id' => $lead4->id]);
    }

    public function test_compound_tokenized_deletion_status_and_domain(): void
    {
        // 1. Same domain, rejected -> SHOULD BE DELETED
        $lead1 = Lead::create([
            'full_name'       => 'Match',
            'corporate_email' => 'match@demodomain.com',
            'status'          => 'rejected',
        ]);

        // 2. Same domain, BUT status is new -> MUST BE PRESERVED
        $lead2 = Lead::create([
            'full_name'       => 'Keep New',
            'corporate_email' => 'keep@demodomain.com',
            'status'          => 'new',
        ]);

        // 3. Different domain, BUT status is rejected -> MUST BE PRESERVED
        $lead3 = Lead::create([
            'full_name'       => 'Keep Rejected Other',
            'corporate_email' => 'rejected@otherdomain.com',
            'status'          => 'rejected',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-webhook-secret',
        ])->postJson('/api/v1/webhook/leads/bulk-delete', [
            'status'       => 'rejected',
            'email_domain' => 'demodomain.com',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['deleted_count' => 1]);

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_bulk_tag_with_domain_selector(): void
    {
        $lead1 = Lead::create(['full_name' => 'Alice', 'corporate_email' => 'alice@partner.com']);
        $lead2 = Lead::create(['full_name' => 'Bob', 'corporate_email' => 'bob@partner.com']);
        $lead3 = Lead::create(['full_name' => 'Charlie', 'corporate_email' => 'charlie@other.com']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/leads/bulk-tag', [
            'email_domain' => 'partner.com',
            'add_tags'     => ['Partner'],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['updated_count' => 2]);

        $this->assertTrue($lead1->refresh()->tags()->where('name', 'Partner')->exists());
        $this->assertTrue($lead2->refresh()->tags()->where('name', 'Partner')->exists());
        $this->assertFalse($lead3->refresh()->tags()->where('name', 'Partner')->exists());
    }
}
