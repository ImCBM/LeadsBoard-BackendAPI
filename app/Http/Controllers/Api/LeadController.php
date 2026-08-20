<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Lead;
use App\Services\LeadExportService;
use App\Services\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST API for lead CRUD operations.
 * Protected by Sanctum or API key middleware.
 */
class LeadController extends Controller
{
    public function __construct(
        private LeadIngestionService $ingestionService,
        private LeadExportService $exportService
    ) {}

    /**
     * List leads with filtering, searching, and pagination.
     * 
     * GET /api/v1/leads
     * 
     * Query params: search, industry, title_tier, status, country,
     *               ingestion_channel, date_from, date_to, per_page, sort_by, sort_dir
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', config('leads.per_page', 25)), 100);
        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        // Whitelist sortable columns to prevent SQL injection
        $allowedSorts = [
            'id', 'full_name', 'company_name', 'industry_classification',
            'title_tier', 'status', 'country', 'created_at', 'employee_headcount',
        ];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        if (!in_array(strtolower($sortDir), ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $leads = Lead::query()
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
            ]))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);

        // Append filter params to pagination links
        $leads->appends($request->query());

        return response()->json($leads);
    }

    /**
     * Get a single lead by ID.
     * 
     * GET /api/v1/leads/{id}
     */
    public function show(int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        return response()->json([
            'data' => $lead,
        ]);
    }

    /**
     * Create a new lead via API (not webhook).
     * 
     * POST /api/v1/leads
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $result = $this->ingestionService->ingest(
            $request->all(),
            'api'
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
     * Update an existing lead.
     * 
     * PUT /api/v1/leads/{id}
     */
    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->update($request->validated());

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data'    => $lead->fresh(),
        ]);
    }

    /**
     * Delete a lead.
     * 
     * DELETE /api/v1/leads/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'message' => 'Lead deleted successfully.',
        ]);
    }

    /**
     * Export filtered leads as CSV.
     * 
     * GET /api/v1/leads/export/csv
     * 
     * Accepts the same filter params as index().
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Lead::query()
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
            ]));

        $filename = 'leads_export_' . now()->format('Y-m-d_His') . '.csv';

        return $this->exportService->exportCsv($query, $filename);
    }

    /**
     * Get available filter options (for populating dropdowns).
     * 
     * GET /api/v1/leads/filters
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'industries'    => Lead::whereNotNull('industry_classification')
                                   ->distinct()
                                   ->orderBy('industry_classification')
                                   ->pluck('industry_classification'),
            'title_tiers'   => Lead::TITLE_TIERS,
            'statuses'      => Lead::STATUSES,
            'countries'     => Lead::whereNotNull('country')
                                   ->distinct()
                                   ->orderBy('country')
                                   ->pluck('country'),
            'channels'      => Lead::INGESTION_CHANNELS,
        ]);
    }
}
