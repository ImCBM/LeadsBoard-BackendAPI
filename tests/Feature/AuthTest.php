<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@leadsboard.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@leadsboard.local',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'data' => [
                         'token',
                         'user' => ['id', 'name', 'email', 'role'],
                     ],
                 ]);

        $token = $response->json('data.token');

        // Access /api/v1/auth/me with bearer token
        $meRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/auth/me');

        $meRes->assertStatus(200)
              ->assertJsonPath('data.email', 'admin@leadsboard.local');

        // Logout
        $logoutRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/auth/logout');

        $logoutRes->assertStatus(200);
    }
}
