<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiTrace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminable middleware for non-blocking API request audit logging.
 *
 * In handle():
 *   - Generates a unique trace UUID (or reuses X-Request-Id from client).
 *   - Records request start time.
 *   - Attaches X-Trace-Id to response headers.
 *
 * In terminate() (after response is sent):
 *   - Calculates execution duration.
 *   - Persists trace record to api_traces table asynchronously.
 *   - Sanitizes sensitive fields from payload logging.
 */
class ApiTraceLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('tiers.traces.enabled', true)) {
            return $next($request);
        }

        // Generate or reuse trace ID
        $traceId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('trace_start_time', hrtime(true));

        /** @var Response $response */
        $response = $next($request);

        // Attach trace ID to response headers
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }

    /**
     * Write trace record after the response has been sent to the client.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (!config('tiers.traces.enabled', true)) {
            return;
        }

        $traceId = $request->attributes->get('trace_id');
        $startTime = $request->attributes->get('trace_start_time');

        if (!$traceId || !$startTime) {
            return;
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        $user = $request->user();

        $traceData = [
            'trace_id' => $traceId,
            'api_key_id' => $apiKey?->id,
            'user_id' => $user?->id,
            'key_prefix' => $apiKey?->plain_text_prefix,
            'method' => $request->method(),
            'path' => Str::limit($request->path(), 255),
            'route_name' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit($request->userAgent(), 500),
        ];

        // Optionally log query params
        $queryParams = $request->query();
        if (!empty($queryParams)) {
            $traceData['query_params'] = $this->sanitize($queryParams);
        }

        // Optionally log request payload (for POST/PUT/PATCH)
        if (config('tiers.traces.log_request_payload', false)
            && in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $payload = $request->except(['_token']);
            $traceData['request_payload'] = $this->sanitize($payload);
        }

        try {
            ApiTrace::create($traceData);
        } catch (\Throwable $e) {
            // Silently fail — tracing should never break API responses
            \Illuminate\Support\Facades\Log::warning('API trace logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove sensitive fields from data before logging.
     */
    private function sanitize(array $data): array
    {
        $sensitiveFields = config('tiers.traces.sanitize_fields', [
            'password', 'password_confirmation', 'token', 'secret', 'api_key', 'authorization',
        ]);

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
