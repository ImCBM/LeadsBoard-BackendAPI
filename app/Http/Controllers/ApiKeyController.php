<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\Request;

/**
 * Web-based API Key CRUD for the internal admin dashboard.
 *
 * Updated to support the new key type, scope, and tier system.
 */
class ApiKeyController extends Controller
{
    public function __construct(
        private ApiKeyService $apiKeyService
    ) {}

    /**
     * Display a listing of the API keys.
     */
    public function index()
    {
        $apiKeys = ApiKey::with('user')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('dashboard.api-keys.index', compact('apiKeys'));
    }

    /**
     * Generate and store a newly created API key.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key_type' => 'nullable|in:public,secret',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:50',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
            'tier' => 'nullable|string|max:32',
        ]);

        $validated['key_type'] = $validated['key_type'] ?? 'secret';

        $result = $this->apiKeyService->createKey($validated);

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key created successfully.')
            ->with('new_api_key', $result['plain_text_key']); // Flash plain key just once
    }

    /**
     * Update the specified API key in storage.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:50',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
            'tier' => 'nullable|string|max:32',
        ]);

        // Resolve scopes if provided
        if (isset($validated['scopes'])) {
            $validated['scopes'] = $this->apiKeyService->resolveScopes(
                $validated['scopes'],
                $apiKey->key_type
            );
        }

        $apiKey->update(array_merge($validated, [
            'is_active' => $request->has('is_active'),
        ]));

        // Clear cached key
        \Illuminate\Support\Facades\Cache::forget("api_key:{$apiKey->key}");

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key updated successfully.');
    }

    /**
     * Remove the specified API key from storage.
     */
    public function destroy(ApiKey $apiKey)
    {
        // Clear cached key
        \Illuminate\Support\Facades\Cache::forget("api_key:{$apiKey->key}");

        $apiKey->delete();

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key revoked and deleted successfully.');
    }
}
