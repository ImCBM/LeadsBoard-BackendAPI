<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiTrace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for developers to query their API call traces.
 *
 * Endpoints:
 *   GET /api/v1/developer/traces — List traces for the developer's keys
 */
class ApiTraceController extends Controller
{
    /**
     * List traces for the authenticated user's API keys.
     *
     * Supports filtering by:
     * - api_key_id: Filter by specific key
     * - method: HTTP method (GET, POST, etc.)
     * - status_code: Response status code
     * - path: Path pattern (partial match)
     * - from / to: Date range
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 50), 100);

        // Get all key IDs owned by this user
        $keyIds = $user->apiKeys()->pluck('id');

        $query = ApiTrace::where(function ($q) use ($keyIds, $user) {
            $q->whereIn('api_key_id', $keyIds)
                ->orWhere('user_id', $user->id);
        });

        // Filters
        if ($request->filled('api_key_id')) {
            // Ensure they can only filter by their own keys
            $requestedKeyId = (int) $request->input('api_key_id');
            if ($keyIds->contains($requestedKeyId)) {
                $query->where('api_key_id', $requestedKeyId);
            }
        }

        if ($request->filled('method')) {
            $query->where('method', strtoupper($request->input('method')));
        }

        if ($request->filled('status_code')) {
            $query->where('status_code', (int) $request->input('status_code'));
        }

        if ($request->filled('path')) {
            $query->where('path', 'LIKE', '%' . $request->input('path') . '%');
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $traces = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $traces->appends($request->query());

        return response()->json($traces);
    }

    /**
     * Get a single trace by trace_id.
     */
    public function show(Request $request, string $traceId): JsonResponse
    {
        $user = $request->user();
        $keyIds = $user->apiKeys()->pluck('id');

        $trace = ApiTrace::where('trace_id', $traceId)
            ->where(function ($q) use ($keyIds, $user) {
                $q->whereIn('api_key_id', $keyIds)
                    ->orWhere('user_id', $user->id);
            })
            ->firstOrFail();

        return response()->json([
            'data' => $trace,
        ]);
    }
}
