<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * Display a listing of the API keys.
     */
    public function index()
    {
        $apiKeys = ApiKey::orderBy('created_at', 'desc')->get();
        return view('dashboard.api-keys.index', compact('apiKeys'));
    }

    /**
     * Generate and store a newly created API key.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
        ]);

        $plainTextKey = Str::random(32);
        $hashedKey = hash('sha256', $plainTextKey);

        $apiKey = ApiKey::create([
            'name' => $request->input('name'),
            'key' => $hashedKey,
            'plain_text_prefix' => substr($plainTextKey, 0, 8),
            // Default rate limit 0 (unlimited) if not specified
            'rate_limit_per_minute' => $request->input('rate_limit_per_minute', 0),
            'is_active' => true,
        ]);

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key created successfully.')
            ->with('new_api_key', $plainTextKey); // Flash plain key just once
    }

    /**
     * Update the specified API key in storage.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'rate_limit_per_minute' => 'nullable|integer|min:0',
        ]);

        $apiKey->update([
            'name' => $request->input('name'),
            'rate_limit_per_minute' => $request->input('rate_limit_per_minute', 0),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key updated successfully.');
    }

    /**
     * Remove the specified API key from storage.
     */
    public function destroy(ApiKey $apiKey)
    {
        $apiKey->delete();

        return redirect()->route('dashboard.api-keys.index')
            ->with('success', 'API Key revoked and deleted successfully.');
    }
}
