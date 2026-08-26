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

        return response()->json([
            'data' => [
                'total_leads'  => Lead::count(),
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
