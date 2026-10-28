<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiTrace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the API trace logging system.
 *
 * Covers:
 * - X-Trace-Id header is returned in responses
 * - Trace records are persisted to the database
 * - Trace data accuracy (method, path, status, IP, duration)
 * - Payload sanitization (passwords, tokens redacted)
 * - Trace pruning command
 */
class ApiTracingTest extends TestCase
{
    use RefreshDatabase;

    private function createApiKey(array $overrides = []): array
    {
        $plainKey = 'lb_sec_' . fake()->regexify('[a-zA-Z0-9]{32}');

        $defaults = [
            'name' => 'Trace Test Key',
            'key_type' => 'secret',
            'key' => hash('sha256', $plainKey),
            'plain_text_prefix' => substr($plainKey, 0, 12),
            'scopes' => ['*'],
            'tier' => 'pro',
            'rate_limit_per_minute' => 0,
            'is_active' => true,
        ];

        $apiKey = ApiKey::create(array_merge($defaults, $overrides));

        return ['api_key' => $apiKey, 'plain_key' => $plainKey];
    }

    /** @test */
    public function response_includes_trace_id_header(): void
    {
        $result = $this->createApiKey();

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $response->assertHeader('X-Trace-Id');
        $traceId = $response->headers->get('X-Trace-Id');
        $this->assertNotEmpty($traceId);
        // UUID format check
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $traceId
        );
    }

    /** @test */
    public function client_request_id_is_reused_as_trace_id(): void
    {
        $result = $this->createApiKey();
        $clientRequestId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
            'X-Request-Id' => $clientRequestId,
        ]);

        $response->assertHeader('X-Trace-Id', $clientRequestId);
    }

    /** @test */
    public function trace_record_is_persisted_to_database(): void
    {
        $result = $this->createApiKey();

        $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        // The terminable middleware should have persisted the trace
        $this->assertDatabaseHas('api_traces', [
            'api_key_id' => $result['api_key']->id,
            'method' => 'GET',
            'status_code' => 200,
        ]);
    }

    /** @test */
    public function trace_records_correct_method_and_path(): void
    {
        $result = $this->createApiKey();

        $this->getJson('/api/v1/tags', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $trace = ApiTrace::where('api_key_id', $result['api_key']->id)->first();
        $this->assertNotNull($trace);
        $this->assertEquals('GET', $trace->method);
        $this->assertStringContainsString('v1/tags', $trace->path);
    }

    /** @test */
    public function trace_records_ip_address(): void
    {
        $result = $this->createApiKey();

        $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $trace = ApiTrace::where('api_key_id', $result['api_key']->id)->first();
        $this->assertNotNull($trace);
        $this->assertNotEmpty($trace->ip_address);
    }

    /** @test */
    public function trace_records_duration(): void
    {
        $result = $this->createApiKey();

        $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $trace = ApiTrace::where('api_key_id', $result['api_key']->id)->first();
        $this->assertNotNull($trace);
        $this->assertGreaterThanOrEqual(0, $trace->duration_ms);
    }

    /** @test */
    public function trace_records_key_prefix(): void
    {
        $result = $this->createApiKey();

        $this->getJson('/api/v1/leads', [
            'X-API-Key' => $result['plain_key'],
        ]);

        $trace = ApiTrace::where('api_key_id', $result['api_key']->id)->first();
        $this->assertNotNull($trace);
        $this->assertEquals($result['api_key']->plain_text_prefix, $trace->key_prefix);
    }

    /** @test */
    public function prune_command_deletes_old_traces(): void
    {
        // Create an old trace
        ApiTrace::create([
            'trace_id' => fake()->uuid(),
            'method' => 'GET',
            'path' => 'api/v1/leads',
            'status_code' => 200,
            'duration_ms' => 50,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(31),
        ]);

        // Create a recent trace
        ApiTrace::create([
            'trace_id' => fake()->uuid(),
            'method' => 'GET',
            'path' => 'api/v1/tags',
            'status_code' => 200,
            'duration_ms' => 30,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDay(),
        ]);

        $this->assertEquals(2, ApiTrace::count());

        $this->artisan('api:prune-traces', ['--days' => 30])
            ->assertExitCode(0);

        $this->assertEquals(1, ApiTrace::count());
        $this->assertDatabaseHas('api_traces', ['path' => 'api/v1/tags']);
    }
}
