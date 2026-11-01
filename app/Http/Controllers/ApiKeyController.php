<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ApiScope;
use App\Models\Tier;
use App\Services\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Web-based API Key CRUD for the internal admin dashboard (Blade).
 *
 * Internal developers & admins have unrestricted access:
 * - Can assign any normalized tier
 * - Can assign custom rate limits / unlimited
 * - Can create system automation keys (e.g. n8n webhooks) with wildcard scopes
 */
class ApiKeyController extends Controller
{
    public function __construct(
        private ApiKeyService $apiKeyService
    ) {}

    /**
     * Display a listing of all API keys across the system.
     */
    public function index()
    {
        $apiKeys = ApiKey::with(['user', 'tier', 'scopes'])
            ->orderBy('created_at', 'desc')
            ->get();

        $tiers = Tier::orderBy('rate_limit_per_minute', 'asc')->get();
        $scopes = ApiScope::where('name', '!=', '*')->get();

        return view('dashboard.api-keys.index', compact('apiKeys', 'tiers', 'scopes'));
    }

    /**
     * Generate and store a newly created internal API key.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key_type' => 'nullable|in:public,secret',
            'tier_id' => 'nullable|exists:tiers,id',
            'tier' => 'nullable|string',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
            'scopes' => 'nullable|array',
        ]);

        if (empty($validated['tier_id']) && !empty($validated['tier'])) {
            $t = Tier::where('slug', $validated['tier'])->first();
            $validated['tier_id'] = $t?->id;
        }

        $validated['key_type'] = $validated['key_type'] ?? 'secret';

        // Internal developers default to full access wildcard if no scopes given
        if (empty($validated['scopes'])) {
            $validated['scopes'] = ['*'];
        }

        $result = $this->apiKeyService->createKey($validated, $request->user());

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key created successfully.')
            ->with('new_api_key', $result['plain_text_key']);
    }

    /**
     * Update the specified API key in storage.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
            'tier_id' => 'nullable|exists:tiers,id',
        ]);

        $apiKey->update([
            'name' => $validated['name'],
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? null,
            'tier_id' => $validated['tier_id'] ?? $apiKey->tier_id,
            'is_active' => $request->has('is_active'),
        ]);

        Cache::forget("api_key:{$apiKey->key}");

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key updated successfully.');
    }

    /**
     * Remove (revoke) the specified API key.
     */
    public function destroy(ApiKey $apiKey)
    {
        Cache::forget("api_key:{$apiKey->key}");
        $apiKey->delete();

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key revoked and deleted successfully.');
    }
}
