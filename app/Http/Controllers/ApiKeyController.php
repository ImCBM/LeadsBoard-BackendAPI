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
        $scopes = ApiScope::where('name', '!=', '*')->orderBy('name')->get();
        $scopesByResource = $scopes->groupBy('resource');

        return view('dashboard.api-keys.index', compact('apiKeys', 'tiers', 'scopes', 'scopesByResource'));
    }

    /**
     * Generate and store a newly created internal API key with full granular control.
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
            'scopes.*' => 'string|max:50',
            'allowed_ips' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        if (empty($validated['tier_id']) && !empty($validated['tier'])) {
            $t = Tier::where('slug', $validated['tier'])->first();
            $validated['tier_id'] = $t?->id;
        }

        $validated['key_type'] = $validated['key_type'] ?? 'secret';

        // Parse allowed_ips from comma/newline separated string
        if (!empty($validated['allowed_ips'])) {
            $ips = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $validated['allowed_ips'])));
            $validated['allowed_ips'] = !empty($ips) ? array_values($ips) : null;
        } else {
            $validated['allowed_ips'] = null;
        }

        // Internal developers default to full access wildcard if no scopes given
        if (empty($validated['scopes'])) {
            $validated['scopes'] = ['*'];
        }

        $result = $this->apiKeyService->createKey($validated, $request->user());

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key created successfully with granular permissions.')
            ->with('new_api_key', $result['plain_text_key']);
    }

    /**
     * Update the specified API key in storage with full granular control.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
            'tier_id' => 'nullable|exists:tiers,id',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:50',
            'allowed_ips' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        $allowedIps = $apiKey->allowed_ips;
        if ($request->has('allowed_ips')) {
            if (!empty($validated['allowed_ips'])) {
                $ips = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $validated['allowed_ips'])));
                $allowedIps = !empty($ips) ? array_values($ips) : null;
            } else {
                $allowedIps = null;
            }
        }

        $apiKey->update([
            'name' => $validated['name'],
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? null,
            'tier_id' => $validated['tier_id'] ?? $apiKey->tier_id,
            'allowed_ips' => $allowedIps,
            'expires_at' => array_key_exists('expires_at', $validated) ? $validated['expires_at'] : $apiKey->expires_at,
            'is_active' => $request->has('is_active'),
        ]);

        if ($request->has('scopes') || $request->has('scopes_submitted')) {
            $scopeNames = $validated['scopes'] ?? [];

            // Public keys can never possess wildcard (*) or mutation scopes
            if ($apiKey->isPublic()) {
                $forbidden = array_merge(['*'], \App\Enums\ApiKeyType::Public->forbiddenScopes());
                $scopeNames = array_values(array_diff($scopeNames, $forbidden));
            }

            if (in_array('*', $scopeNames)) {
                $wildcardScope = ApiScope::firstOrCreate(
                    ['name' => '*'],
                    ['resource' => '*', 'action' => '*', 'description' => 'Full access wildcard']
                );
                $apiKey->scopes()->sync([$wildcardScope->id]);
            } else {
                $scopeIds = ApiScope::whereIn('name', $scopeNames)->pluck('id')->all();
                $apiKey->scopes()->sync($scopeIds);
            }
        }

        Cache::forget("api_key:{$apiKey->key}");

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key permissions and settings updated successfully.');
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
