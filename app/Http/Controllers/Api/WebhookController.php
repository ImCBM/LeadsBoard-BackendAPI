<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\BulkStoreLeadRequest;
use App\Services\LeadIngestionService;
use Illuminate\Http\JsonResponse;

/**
 * Webhook endpoint for n8n and external services.
 * 
 * This is the primary entry point for lead data.
 * Protected by ValidateWebhookToken middleware.
 */
class WebhookController extends Controller
{
    public function __construct(
        private LeadIngestionService $ingestionService
    ) {}

    /**
     * Ingest a single lead.
     * 
     * POST /api/v1/webhook/leads
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $result = $this->ingestionService->ingest(
            $request->all(),
            'n8n'
        );

        if ($result['duplicate']) {
            return response()->json([
                'message' => 'Duplicate lead — this email already exists.',
                'errors'  => $result['errors'],
            ], 409);
        }

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to create lead.',
                'errors'  => $result['errors'],
            ], 422);
        }

        return response()->json([
            'message' => 'Lead created successfully.',
            'data'    => $result['lead'],
        ], 201);
    }

    /**
     * Ingest multiple leads at once.
     * 
     * POST /api/v1/webhook/leads/bulk
     */
    public function bulkStore(BulkStoreLeadRequest $request): JsonResponse
    {
        $result = $this->ingestionService->bulkIngest(
            $request->input('leads'),
            'n8n'
        );

        $statusCode = $result['errors'] > 0 ? 207 : 201; // 207 = Multi-Status

        return response()->json([
            'message'    => "Bulk import complete: {$result['inserted']} inserted, {$result['duplicates']} duplicates, {$result['errors']} errors.",
            'summary'    => [
                'inserted'   => $result['inserted'],
                'duplicates' => $result['duplicates'],
                'errors'     => $result['errors'],
                'total'      => count($request->input('leads')),
            ],
            'results'    => $result['results'],
        ], $statusCode);
    }
}
