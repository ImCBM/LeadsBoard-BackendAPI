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
}
