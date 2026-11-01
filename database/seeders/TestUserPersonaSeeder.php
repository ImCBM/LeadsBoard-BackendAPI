<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Subscription;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the test persona accounts and deterministic webhook test key.
 *
 * Personas:
 * - admin@leadsboard.local (Admin / Internal Developer)
 * - free@leadsboard.local (Free Client, active free tier)
 * - pro@leadsboard.local (Pro Client, active pro tier)
 * - enterprise@leadsboard.local (Enterprise Client, active enterprise tier)
 *
 * All passwords: 'password'
 *
 * Deterministic API Key:
 * - Plaintext: 'lb_sec_n8n_test_automation_key'
 * - Scope: 'webhooks:post'
 */
class TestUserPersonaSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        $freeTier = Tier::where('slug', 'free')->firstOrFail();
        $proTier = Tier::where('slug', 'pro')->firstOrFail();
        $enterpriseTier = Tier::where('slug', 'enterprise')->firstOrFail();

        // ── 1. Admin Persona ─────────────────────────────────────────
        $admin = User::updateOrCreate(
            ['email' => 'admin@leadsboard.local'],
            [
                'name' => 'Admin Developer',
                'password' => $defaultPassword,
                'role' => 'admin',
            ]
        );

        // ── 2. Free Persona ──────────────────────────────────────────
        $freeUser = User::updateOrCreate(
            ['email' => 'free@leadsboard.local'],
            [
                'name' => 'Free Client User',
                'password' => $defaultPassword,
                'role' => 'client',
            ]
        );

        Subscription::updateOrCreate(
            ['user_id' => $freeUser->id],
            [
                'tier_id' => $freeTier->id,
                'status' => 'active',
                'starts_at' => now(),
            ]
        );

        // ── 3. Pro Persona ───────────────────────────────────────────
        $proUser = User::updateOrCreate(
            ['email' => 'pro@leadsboard.local'],
            [
                'name' => 'Pro Client User',
                'password' => $defaultPassword,
                'role' => 'client',
            ]
        );

        Subscription::updateOrCreate(
            ['user_id' => $proUser->id],
            [
                'tier_id' => $proTier->id,
                'status' => 'active',
                'starts_at' => now(),
            ]
        );

        // ── 4. Enterprise Persona ────────────────────────────────────
        $enterpriseUser = User::updateOrCreate(
            ['email' => 'enterprise@leadsboard.local'],
            [
                'name' => 'Enterprise Client User',
                'password' => $defaultPassword,
                'role' => 'client',
            ]
        );

        Subscription::updateOrCreate(
            ['user_id' => $enterpriseUser->id],
            [
                'tier_id' => $enterpriseTier->id,
                'status' => 'active',
                'starts_at' => now(),
            ]
        );

        // ── 5. Deterministic n8n Automation Webhook Key ──────────────
        $rawKey = 'lb_sec_n8n_test_automation_key';
        $hashed = hash('sha256', $rawKey);

        $webhookKey = ApiKey::updateOrCreate(
            ['key' => $hashed],
            [
                'user_id' => $admin->id,
                'tier_id' => $enterpriseTier->id,
                'name' => 'n8n Test Ingestion Automation',
                'key_type' => 'secret',
                'plain_text_prefix' => 'lb_sec_n8n',
                'rate_limit_per_minute' => $enterpriseTier->rate_limit_per_minute,
                'is_active' => true,
            ]
        );

        $webhookScope = ApiScope::where('name', 'webhooks:post')->first();
        if ($webhookScope) {
            $webhookKey->scopes()->syncWithoutDetaching([$webhookScope->id]);
        }
    }
}
