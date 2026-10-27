<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard statistics and summary endpoints.
 */
class DashboardStatsController extends Controller
{
    /**
     * High-level summary counts.
     * 
     * GET /api/v1/stats/summary
     */
    public function summary(): JsonResponse
    {
        $now = now();

        $totalLeads = Lead::count();
        $batchDuplicates = (int) \App\Models\IngestionBatch::sum('duplicates_count');
        $batchErrors = (int) \App\Models\IngestionBatch::sum('errors_count');
        $batchTotalRecords = (int) \App\Models\IngestionBatch::sum('total_records');

        // Data Quality & Incomplete Records (Spec requirement: missing/null records)
        $missingPhone = Lead::whereNull('contact_number')->orWhere('contact_number', '')->count();
        $missingLinkedin = Lead::whereNull('executive_linkedin_url')->orWhere('executive_linkedin_url', '')->count();
        $unverifiedEmail = Lead::whereNull('email_status')
            ->orWhere('email_status', 'NOT LIKE', '%Valid%')
            ->count();
        $missingDomain = Lead::whereDoesntHave('company', fn($cq) => $cq->whereNotNull('clean_root_domain')->where('clean_root_domain', '!=', ''))->count();

        $incompleteCount = Lead::where(function ($q) {
            $q->whereNull('contact_number')
              ->orWhere('contact_number', '')
              ->orWhereNull('executive_linkedin_url')
              ->orWhere('executive_linkedin_url', '')
              ->orWhereNull('job_title')
              ->orWhere('job_title', '')
              ->orWhereNull('email_status')
              ->orWhere('email_status', 'NOT LIKE', '%Valid%');
        })->count();

        // Source channel counts
        $channelCounts = [
            'n8n'         => Lead::where('ingestion_channel', 'n8n')->count(),
            'csv_upload'  => Lead::where('ingestion_channel', 'csv_upload')->count(),
            'csv_import'  => Lead::where('ingestion_channel', 'csv_import')->count(),
            'api'         => Lead::where('ingestion_channel', 'api')->count(),
            'manual'      => Lead::where('ingestion_channel', 'manual')->count(),
        ];

        // Recent Ingestion Batches (last 5 runs across all sources)
        $recentBatches = \App\Models\IngestionBatch::orderByDesc('created_at')
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'total_leads'  => $totalLeads,
                'today'        => Lead::whereDate('created_at', $now->toDateString())->count(),
                'this_week'    => Lead::whereBetween('created_at', [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek(),
                ])->count(),
                'this_month'   => Lead::whereMonth('created_at', $now->month)
                                      ->whereYear('created_at', $now->year)
                                      ->count(),
                'status_counts' => [
                    'new'       => Lead::where('status', 'new')->count(),
                    'reviewed'  => Lead::where('status', 'reviewed')->count(),
                    'qualified' => Lead::where('status', 'qualified')->count(),
                    'rejected'  => Lead::where('status', 'rejected')->count(),
                ],
                'ingestion_metrics' => [
                    'total_attempts'       => max($batchTotalRecords, $totalLeads + $batchDuplicates),
                    'successful_inserts'   => $totalLeads,
                    'duplicates_prevented' => $batchDuplicates,
                    'errors_count'         => $batchErrors,
                    'by_source'            => $channelCounts,
                ],
                'data_quality' => [
                    'incomplete_records' => $incompleteCount,
                    'complete_records'   => max(0, $totalLeads - $incompleteCount),
                    'missing_phone'      => $missingPhone,
                    'missing_linkedin'   => $missingLinkedin,
                    'unverified_email'   => $unverifiedEmail,
                    'missing_domain'     => $missingDomain,
                ],
                'recent_batches' => $recentBatches,
            ],
        ]);
    }

    /**
     * Lead count grouped by industry.
     * 
     * GET /api/v1/stats/by-industry
     */
    public function byIndustry(): JsonResponse
    {
        $data = Industry::select('industries.name as industry_classification', DB::raw('COUNT(leads.id) as count'))
            ->join('companies', 'companies.industry_id', '=', 'industries.id')
            ->join('leads', 'leads.company_id', '=', 'companies.id')
            ->groupBy('industries.id', 'industries.name')
            ->orderByDesc('count')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * Lead count grouped by title tier.
     * 
     * GET /api/v1/stats/by-title-tier
     */
    public function byTitleTier(): JsonResponse
    {
        $data = Lead::select('title_tier', DB::raw('COUNT(*) as count'))
            ->groupBy('title_tier')
            ->orderByDesc('count')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * Lead count grouped by status.
     * 
     * GET /api/v1/stats/by-status
     */
    public function byStatus(): JsonResponse
    {
        $data = Lead::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * Lead count grouped by country.
     * 
     * GET /api/v1/stats/by-country
     */
    public function byCountry(): JsonResponse
    {
        $data = Country::select('countries.name as country', DB::raw('COUNT(leads.id) as count'))
            ->join('locations', 'locations.country_id', '=', 'countries.id')
            ->join('companies', 'companies.location_id', '=', 'locations.id')
            ->join('leads', 'leads.company_id', '=', 'companies.id')
            ->groupBy('countries.id', 'countries.name')
            ->orderByDesc('count')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * Leads over time (daily counts for the last N days).
     * 
     * GET /api/v1/stats/timeline?days=30
     */
    public function timeline(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 365);
        $startDate = now()->subDays($days)->startOfDay();

        $data = Lead::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'data'  => $data,
            'range' => [
                'from' => $startDate->toDateString(),
                'to'   => now()->toDateString(),
                'days'  => $days,
            ],
        ]);
    }
}
