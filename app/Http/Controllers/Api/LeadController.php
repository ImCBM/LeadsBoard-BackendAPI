<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteLeadsRequest;
use App\Http\Requests\BulkTagLeadsRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Tag;
use App\Services\LeadBulkService;
use App\Services\LeadExportService;
use App\Services\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST API for lead CRUD operations.
 * Protected by Sanctum or API key middleware.
 */
class LeadController extends Controller
{
    public function __construct(
        private LeadIngestionService $ingestionService,
        private LeadExportService $exportService,
        private LeadBulkService $bulkService,
        private \App\Services\LeadImportService $importService
    ) {}

    /**
     * List leads with filtering, searching, and pagination.
     * 
     * GET /api/v1/leads
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', config('leads.per_page', 25)), 100);
        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Lead::query()
            ->with(['company.industry', 'company.location.country', 'tags'])
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
                'headcount_range', 'headcount_min', 'headcount_max',
                'website_status', 'email_status', 'tag', 'tags',
            ]));

        // Handle relational sorting safely
        if ($sortBy === 'company_name') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->orderBy('companies.name', $sortDir)
                  ->select('leads.*');
        } elseif ($sortBy === 'clean_root_domain') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->orderBy('companies.clean_root_domain', $sortDir)
                  ->select('leads.*');
        } elseif ($sortBy === 'website_status') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->orderBy('companies.website_status', $sortDir)
                  ->select('leads.*');
        } elseif ($sortBy === 'industry_classification') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->leftJoin('industries', 'companies.industry_id', '=', 'industries.id')
                  ->orderBy('industries.name', $sortDir)
                  ->select('leads.*');
        } elseif ($sortBy === 'country') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->leftJoin('locations', 'companies.location_id', '=', 'locations.id')
                  ->leftJoin('countries', 'locations.country_id', '=', 'countries.id')
                  ->orderBy('countries.name', $sortDir)
                  ->select('leads.*');
        } elseif ($sortBy === 'employee_headcount') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->orderBy('companies.employee_headcount', $sortDir)
                  ->select('leads.*');
        } elseif (in_array($sortBy, ['id', 'full_name', 'job_title', 'title_tier', 'corporate_email', 'email_status', 'ingestion_channel', 'status', 'created_at'])) {
            $query->orderBy("leads.{$sortBy}", $sortDir);
        } else {
            $query->orderBy('leads.created_at', 'desc');
        }

        $leads = $query->paginate($perPage);
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
        $lead = Lead::with(['company.industry', 'company.location.country', 'tags'])->findOrFail($id);

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
                'message'          => $result['message'] ?? 'Duplicate lead entry detected.',
                'duplicate_field'  => $result['duplicate_field'] ?? null,
                'duplicate_fields' => $result['duplicate_fields'] ?? [],
                'errors'           => $result['errors'],
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
     * Update an existing lead and related company/location info.
     * 
     * PUT /api/v1/leads/{id}
     */
    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $lead = Lead::with(['company.industry', 'company.location.country', 'tags'])->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($lead, $validated, $request) {
            // Update Lead core fields
            $leadData = array_intersect_key($validated, array_flip([
                'full_name', 'job_title', 'title_tier', 'corporate_email',
                'contact_number', 'email_status', 'executive_linkedin_url',
                'status', 'notes',
            ]));
            if (!empty($leadData)) {
                $lead->update($leadData);
            }

            // Sync tags if passed
            if ($request->has('tags') || $request->has('Tags')) {
                $tags = $request->input('tags', $request->input('Tags'));
                $lead->syncTags($tags);
            }

            // Update or create Company details
            $company = $lead->company;
            $companyName = $validated['company_name'] ?? null;
            $cleanDomain = $validated['clean_root_domain'] ?? null;

            if ($companyName || $cleanDomain || $company) {
                if (!$company) {
                    $company = $lead->company()->create([
                        'name' => $companyName ?? 'Unknown Company',
                        'clean_root_domain' => $cleanDomain,
                    ]);
                    $lead->update(['company_id' => $company->id]);
                }

                // Industry
                if (array_key_exists('industry_classification', $validated)) {
                    $industryName = $validated['industry_classification'];
                    $industryId = $industryName ? Industry::firstOrCreate(['name' => $industryName])->id : null;
                    $company->industry_id = $industryId;
                }

                // Location & Country
                if (array_key_exists('hq_location', $validated) || array_key_exists('country', $validated)) {
                    $hq = $validated['hq_location'] ?? $company->location?->raw_location;
                    $countryName = $validated['country'] ?? null;

                    if ($hq) {
                        $country = $countryName ? Country::firstOrCreate(['name' => $countryName]) : null;
                        $location = Location::firstOrCreate(
                            ['raw_location' => $hq],
                            ['country_id' => $country?->id]
                        );
                        $company->location_id = $location->id;
                    }
                }

                if (array_key_exists('company_name', $validated)) {
                    $company->name = $validated['company_name'];
                }
                if (array_key_exists('clean_root_domain', $validated)) {
                    $company->clean_root_domain = $validated['clean_root_domain'];
                }
                if (array_key_exists('website_status', $validated)) {
                    $company->website_status = $validated['website_status'];
                }
                if (array_key_exists('company_linkedin_page', $validated)) {
                    $company->company_linkedin_page = $validated['company_linkedin_page'];
                }
                if (array_key_exists('employee_headcount', $validated)) {
                    $company->employee_headcount = $validated['employee_headcount'];
                }

                $company->save();
            }
        });

        $lead->refresh()->load(['company.industry', 'company.location.country', 'tags']);

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data'    => $lead,
        ]);
    }

    /**
     * Delete a single lead.
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
     * Bulk delete leads matching specified criteria.
     * 
     * POST /api/v1/leads/bulk-delete
     * DELETE /api/v1/leads/bulk
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
     * Bulk apply tags across multiple leads.
     * 
     * POST /api/v1/leads/bulk-tag
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

    /**
     * Import leads via CSV file upload from dashboard.
     * 
     * POST /api/v1/leads/import/csv
     */
    public function importCsv(\App\Http\Requests\ImportCsvLeadsRequest $request): JsonResponse
    {
        $result = $this->importService->importCsv($request->file('file'));
        $status = ($result['summary']['errors'] > 0 && $result['summary']['inserted'] > 0) ? 207 : 200;

        return response()->json($result, $status);
    }

    /**
     * Export filtered leads as CSV.
     * 
     * GET /api/v1/leads/export/csv
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Lead::query()
            ->with(['company.industry', 'company.location.country', 'tags'])
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
                'headcount_range', 'headcount_min', 'headcount_max',
                'website_status', 'email_status', 'tag', 'tags',
            ]));

        $filename = 'leads_export_' . now()->format('Y-m-d_His') . '.csv';

        return $this->exportService->exportCsv($query, $filename);
    }

    /**
     * Get available filter options.
     * 
     * GET /api/v1/leads/filters
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'industries'       => Industry::orderBy('name')->pluck('name'),
            'title_tiers'      => Lead::TITLE_TIERS,
            'statuses'         => Lead::STATUSES,
            'countries'        => Country::orderBy('name')->pluck('name'),
            'channels'         => Lead::INGESTION_CHANNELS,
            'tags'             => Tag::orderBy('name')->get(['id', 'name', 'slug', 'type', 'color']),
            'headcount_ranges' => Lead::HEADCOUNT_RANGES,
            'website_statuses' => [
                ['value' => '200', 'label' => 'Active Website (200 OK)'],
                ['value' => 'error', 'label' => 'Issues / Unreachable'],
            ],
            'email_statuses'   => [
                ['value' => 'valid', 'label' => 'Verified / Valid Email'],
                ['value' => 'invalid', 'label' => 'Invalid / Catch-all'],
            ],
        ]);
    }
}
