<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Tier;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\TierLimiter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for the Dual-Bucket Rate Limiter in TierLimiter and AuthenticateApiRequest middleware.
 *
 * Covers:
 * - Bucket 1: Account-level quota (global per user across all keys)
 * - Bucket 2: Key-level budget (isolated per key)
 * - Daily quota limit enforcement
 * - Rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset)
 *
 * TDD RED phase: Defines the contract for dual-bucket limiting.
 */
class DualBucketRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    private TierLimiter $limiter;
    private ApiKeyService $keyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->limiter = app(TierLimiter::class);
        $this->keyService = app(ApiKeyService::class);
        Cache::flush();
    }

    /** @test */
    public function key_rate_limit_checks_both_account_and_key_buckets(): void
    {
        $proUser = User::where('email', 'pro@leadsboard.local')->first();
        // Pro tier has rate_limit_per_minute = 300

        // Create key with budgeting limit of 20 req/min
        $result = $this->keyService->createKey([
            'name' => 'Budgeted Key',
            'key_type' => 'secret',
            'rate_limit_per_minute' => 20,
            'scopes' => ['leads:read'],
        ], $proUser);

        $apiKey = $result['api_key'];

        // Make 20 requests -> should succeed
        for ($i = 1; $i <= 20; $i++) {
            $check = $this->limiter->checkDualBucketLimit($apiKey);
            $this->assertFalse($check['exceeded'], "Request {$i} should not be exceeded.");
        }

        // 21st request exceeds key-level bucket
        $exceededCheck = $this->limiter->checkDualBucketLimit($apiKey);
        $this->assertTrue($exceededCheck['exceeded']);
        $this->assertEquals('key', $exceededCheck['throttled_by']);
    }

    /** @test */
    public function account_level_bucket_throttles_all_keys_when_aggregate_quota_exhausted(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        // Free tier rate limit is 60 req/min

        // Create 2 keys under Free user
        $key1 = $this->keyService->createKey([
            'name' => 'Key 1',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $freeUser)['api_key'];

        $key2 = $this->keyService->createKey([
            'name' => 'Key 2',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $freeUser)['api_key'];

        // Consume 30 with Key 1
        for ($i = 1; $i <= 30; $i++) {
            $this->limiter->checkDualBucketLimit($key1);
        }

        // Consume 30 with Key 2 -> Total = 60
        for ($i = 1; $i <= 30; $i++) {
            $this->limiter->checkDualBucketLimit($key2);
        }

        // 61st request with Key 1 must be throttled at account level
        $check = $this->limiter->checkDualBucketLimit($key1);
        $this->assertTrue($check['exceeded']);
        $this->assertEquals('account', $check['throttled_by']);
    }

    /** @test */
    public function daily_quota_throttles_when_daily_request_limit_exceeded(): void
    {
        $freeUser = User::where('email', 'free@leadsboard.local')->first();
        $freeTier = $freeUser->getTier(); // daily_request_limit = 5000

        $key = $this->keyService->createKey([
            'name' => 'Quota Test Key',
            'key_type' => 'secret',
            'scopes' => ['leads:read'],
        ], $freeUser)['api_key'];

        // Simulate daily count at limit
        $cacheKey = "api_quota:daily:user:{$freeUser->id}:" . now()->format('Y-m-d');
        Cache::put($cacheKey, $freeTier->daily_request_limit, 86400);

        $check = $this->limiter->checkDualBucketLimit($key);
        $this->assertTrue($check['exceeded']);
        $this->assertEquals('daily_quota', $check['throttled_by']);
    }

    /** @test */
    public function dual_bucket_returns_rate_limit_headers(): void
    {
        $proUser = User::where('email', 'pro@leadsboard.local')->first();

        $key = $this->keyService->createKey([
            'name' => 'Headers Key',
            'key_type' => 'secret',
            'rate_limit_per_minute' => 50,
            'scopes' => ['leads:read'],
        ], $proUser)['api_key'];

        $check = $this->limiter->checkDualBucketLimit($key);

        $this->assertArrayHasKey('limit', $check);
        $this->assertArrayHasKey('remaining', $check);
        $this->assertArrayHasKey('reset', $check);
        $this->assertEquals(50, $check['limit']);
        $this->assertEquals(49, $check['remaining']);
    }
}
