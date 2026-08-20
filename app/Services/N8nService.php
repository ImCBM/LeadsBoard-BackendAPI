<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nService
{
    /**
     * Send a webhook to n8n
     * 
     * @param string $webhookUrl
     * @param array $payload
     * @return bool
     */
    public function triggerWebhook(string $webhookUrl, array $payload): bool
    {
        try {
            $response = Http::post($webhookUrl, $payload);
            
            if ($response->successful()) {
                return true;
            }

            Log::error("n8n webhook failed", [
                'url' => $webhookUrl,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("Exception calling n8n webhook", [
                'url' => $webhookUrl,
                'message' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
