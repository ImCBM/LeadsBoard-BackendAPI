<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/dashboard');
    }

    public function test_login_and_view_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@leadsboard.local',
            'password' => bcrypt('password'),
        ]);

        Lead::create([
            'full_name' => 'Web Dashboard Lead',
            'corporate_email' => 'dashboard@example.com',
            'company_name' => 'Web Corp',
            'title_tier' => 'C-Level',
        ]);

        // Login
        $loginRes = $this->post('/login', [
            'email' => 'admin@leadsboard.local',
            'password' => 'password',
        ]);
        $loginRes->assertRedirect('/dashboard');

        // View dashboard
        $dashRes = $this->actingAs($user)->get('/dashboard');
        $dashRes->assertStatus(200)
                ->assertSee('Leads Dashboard')
                ->assertSee('Web Dashboard Lead')
                ->assertSee('dashboard@example.com');

        // Web CSV Export
        $exportRes = $this->actingAs($user)->get('/dashboard/export/csv');
        $exportRes->assertStatus(200)
                  ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_view_api_keys_dashboard_with_granular_scopes(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::where('email', 'admin@leadsboard.local')->first();

        $response = $this->actingAs($admin)->get('/dashboard/api-keys');

        $response->assertStatus(200)
            ->assertSee('API Keys')
            ->assertSee('Create API Key')
            ->assertSee('Full Access Wildcard (*)')
            ->assertSee('Scopes');
    }

    public function test_admin_can_create_api_key_with_granular_scopes_and_ip_whitelist(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::where('email', 'admin@leadsboard.local')->first();
        $developerTier = \App\Models\Tier::where('slug', 'developer')->first();

        $response = $this->actingAs($admin)->post('/dashboard/api-keys', [
            'name' => 'Internal Worker Key',
            'key_type' => 'secret',
            'tier_id' => $developerTier->id,
            'rate_limit_per_minute' => 0,
            'scopes' => ['leads:read', 'webhooks:post'],
            'allowed_ips' => '127.0.0.1, 10.0.0.1',
        ]);

        $response->assertRedirect('/dashboard/api-keys');
        $response->assertSessionHas('new_api_key');

        $createdKey = \App\Models\ApiKey::where('name', 'Internal Worker Key')->first();
        $this->assertNotNull($createdKey);
        $this->assertEquals(['127.0.0.1', '10.0.0.1'], $createdKey->allowed_ips);
        $this->assertEquals(0, $createdKey->rate_limit_per_minute);
        $this->assertTrue($createdKey->hasScope('leads:read'));
        $this->assertTrue($createdKey->hasScope('webhooks:post'));
        $this->assertFalse($createdKey->hasScope('leads:delete'));
    }

    public function test_admin_can_update_api_key_scopes_and_settings(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::where('email', 'admin@leadsboard.local')->first();
        $key = \App\Models\ApiKey::first();

        $response = $this->actingAs($admin)->put("/dashboard/api-keys/{$key->id}", [
            'name' => 'Updated Automation Key',
            'rate_limit_per_minute' => 500,
            'tier_id' => $key->tier_id,
            'scopes' => ['leads:read'],
            'scopes_submitted' => '1',
            'allowed_ips' => '192.168.1.50',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/dashboard/api-keys');
        $key->refresh();

        $this->assertEquals('Updated Automation Key', $key->name);
        $this->assertEquals(500, $key->rate_limit_per_minute);
        $this->assertEquals(['192.168.1.50'], $key->allowed_ips);
        $this->assertEquals(['leads:read'], $key->scopes->pluck('name')->all());
    }

    public function test_admin_can_revoke_api_key(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $admin = User::where('email', 'admin@leadsboard.local')->first();
        $key = \App\Models\ApiKey::first();

        $response = $this->actingAs($admin)->delete("/dashboard/api-keys/{$key->id}");

        $response->assertRedirect('/dashboard/api-keys');
        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }
}
