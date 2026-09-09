<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteLeadsRequest;
use App\Http\Requests\BulkStoreLeadRequest;
use App\Http\Requests\BulkTagLeadsRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Services\LeadBulkService;
use App\Services\LeadIngestionService;
use Illuminate\Http\JsonResponse;

/**
 * Webhook endpoint for n8n and external services.
 * 
 * This is the primary entry point for automated lead ingestion,
 * bulk deletion, and bulk tagging.
 * Protected by ValidateWebhookToken middleware.
 */
class WebhookController extends Controller
{
    public function __construct(
        private LeadIngestionService $ingestionService,
        private LeadBulkService $bulkService
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

    /**
     * Bulk delete leads matching specified criteria via webhook.
     * 
     * POST /api/v1/webhook/leads/bulk-delete
     */
    public function bulkDelete(BulkDeleteLeadsRequest $request): JsonResponse
    {
        $result = $this->bulkService->bulkDelete($request->validated());

        return response()->json([
            'message'       => "Bulk delete complete: {$result['deleted_count']} leads deleted.",
            'deleted_count' => $result['deleted_count'],
            'deleted_ids'   => $result['deleted_ids'],
            'criteria'      => $result['criteria'],
        ]);
    }

    /**
     * Bulk apply tags across multiple leads via webhook.
     * 
     * POST /api/v1/webhook/leads/bulk-tag
     */
    public function bulkTag(BulkTagLeadsRequest $request): JsonResponse
    {
        $result = $this->bulkService->bulkTag($request->validated());

        return response()->json([
            'message'       => "Bulk tag complete: {$result['updated_count']} leads updated.",
            'updated_count' => $result['updated_count'],
            'lead_ids'      => $result['lead_ids'],
            'operations'    => $result['operations'],
        ]);
    }
}
