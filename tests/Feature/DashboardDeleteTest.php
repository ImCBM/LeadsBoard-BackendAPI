<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_delete_lead(): void
    {
        $lead = Lead::create([
            'full_name' => 'Protected Lead',
            'corporate_email' => 'protected@example.com',
            'company_name' => 'Protected Corp',
        ]);

        $response = $this->delete("/dashboard/leads/{$lead->id}");
        $response->assertRedirect('/login');

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_guest_cannot_bulk_delete_leads(): void
    {
        $response = $this->post('/dashboard/leads/bulk-delete', [
            'lead_ids' => [1, 2],
        ]);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_delete_single_lead_and_preserves_company(): void
    {
        $company = Company::create([
            'name' => 'Acme Corp',
            'domain' => 'acme.com',
            'industry' => 'Technology',
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'full_name' => 'John Doe',
            'corporate_email' => 'john.doe@acme.com',
            'company_name' => 'Acme Corp',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/dashboard/leads/{$lead->id}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', "Lead #{$lead->id} (John Doe) was permanently deleted.");

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_authenticated_user_can_bulk_delete_by_ids(): void
    {
        $lead1 = Lead::create([
            'full_name' => 'Lead One',
            'corporate_email' => 'lead1@example.com',
            'company_name' => 'Corp One',
        ]);

        $lead2 = Lead::create([
            'full_name' => 'Lead Two',
            'corporate_email' => 'lead2@example.com',
            'company_name' => 'Corp Two',
        ]);

        $lead3 = Lead::create([
            'full_name' => 'Lead Three',
            'corporate_email' => 'lead3@example.com',
            'company_name' => 'Corp Three',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/leads/bulk-delete', [
                'lead_ids' => [$lead1->id, $lead2->id],
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', 'Bulk cleanup complete: 2 leads permanently removed.');

        $this->assertDatabaseMissing('leads', ['id' => $lead1->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead2->id]);
        $this->assertDatabaseHas('leads', ['id' => $lead3->id]);
    }

    public function test_authenticated_user_can_bulk_delete_by_domain(): void
    {
        Lead::create([
            'full_name' => 'Target Lead',
            'corporate_email' => 'tim@apple.com',
            'company_name' => 'Apple',
        ]);

        $keptLead = Lead::create([
            'full_name' => 'Kept Lead',
            'corporate_email' => 'sundar@google.com',
            'company_name' => 'Google',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/leads/bulk-delete', [
                'email_domain' => 'apple.com',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', 'Bulk cleanup complete: 1 lead permanently removed.');

        $this->assertDatabaseMissing('leads', ['corporate_email' => 'tim@apple.com']);
        $this->assertDatabaseHas('leads', ['id' => $keptLead->id]);
    }

    public function test_authenticated_user_can_wipe_all_leads_with_confirm(): void
    {
        Lead::create([
            'full_name' => 'Lead Alpha',
            'corporate_email' => 'alpha@example.com',
            'company_name' => 'Alpha Corp',
        ]);

        Lead::create([
            'full_name' => 'Lead Beta',
            'corporate_email' => 'beta@example.com',
            'company_name' => 'Beta Corp',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/dashboard/leads/bulk-delete', [
                'confirm' => '1',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', 'Bulk cleanup complete: 2 leads permanently removed.');

        $this->assertEquals(0, Lead::count());
    }
}
