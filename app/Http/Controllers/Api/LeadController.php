<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Location;
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
        private LeadExportService $exportService
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
            ->with(['company.industry', 'company.location.country'])
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
            ]));

        // Handle relational sorting safely
        if ($sortBy === 'company_name') {
            $query->leftJoin('companies', 'leads.company_id', '=', 'companies.id')
                  ->orderBy('companies.name', $sortDir)
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
        } elseif (in_array($sortBy, ['id', 'full_name', 'title_tier', 'status', 'created_at'])) {
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
        $lead = Lead::with(['company.industry', 'company.location.country'])->findOrFail($id);

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
     * Update an existing lead and related company/location info.
     * 
     * PUT /api/v1/leads/{id}
     */
    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $lead = Lead::with(['company.industry', 'company.location.country'])->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($lead, $validated) {
            // Update Lead core fields
            $leadData = array_intersect_key($validated, array_flip([
                'full_name', 'job_title', 'title_tier', 'corporate_email',
                'email_status', 'executive_linkedin_url', 'status', 'notes',
            ]));
            if (!empty($leadData)) {
                $lead->update($leadData);
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

        $lead->refresh()->load(['company.industry', 'company.location.country']);

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data'    => $lead,
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
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Lead::query()
            ->with(['company.industry', 'company.location.country'])
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
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
            'industries'  => Industry::orderBy('name')->pluck('name'),
            'title_tiers' => Lead::TITLE_TIERS,
            'statuses'    => Lead::STATUSES,
            'countries'   => Country::orderBy('name')->pluck('name'),
            'channels'    => Lead::INGESTION_CHANNELS,
        ]);
    }
}
