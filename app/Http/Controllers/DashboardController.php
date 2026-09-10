<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadBulkService;
use App\Services\LeadExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Serves the minimal Blade dashboard views.
 * Uses session-based auth (standard Laravel web auth).
 */
class DashboardController extends Controller
{
    public function __construct(
        private LeadExportService $exportService,
        private LeadBulkService $bulkService
    ) {}

    /**
     * Show login page.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('dashboard.login');
    }

    /**
     * Handle login form submission.
     */
    public function login(Request $request)
    {
        $user = User::first();
        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'No admin user found in database. Please run migrations and seeders.',
        ]);
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Main dashboard view — leads table with filters and stats.
     */
    public function index(Request $request)
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
        } elseif (in_array($sortBy, ['full_name', 'job_title', 'title_tier', 'corporate_email', 'status', 'created_at'])) {
            $query->orderBy("leads.{$sortBy}", $sortDir);
        } else {
            $query->orderBy('leads.created_at', 'desc');
        }

        $leads = $query->paginate($perPage)->appends($request->query());

        // Summary stats
        $stats = [
            'total'      => Lead::count(),
            'today'      => Lead::whereDate('created_at', today())->count(),
            'this_week'  => Lead::whereBetween('created_at', [
                now()->startOfWeek(), now()->endOfWeek(),
            ])->count(),
            'this_month' => Lead::whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year)
                                ->count(),
        ];

        // Filter options for dropdowns
        $filterOptions = [
            'industries'  => Industry::orderBy('name')->pluck('name'),
            'countries'   => Country::orderBy('name')->pluck('name'),
            'title_tiers' => Lead::TITLE_TIERS,
            'statuses'    => Lead::STATUSES,
        ];

        return view('dashboard.index', compact('leads', 'stats', 'filterOptions'));
    }

    /**
     * Export filtered leads as CSV (from dashboard).
     */
    public function exportCsv(Request $request)
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
     * Delete a single lead from the dashboard.
     */
    public function destroy(int $id)
    {
        $lead = Lead::findOrFail($id);
        $name = $lead->full_name;
        $lead->delete();

        return redirect()->route('dashboard')->with('success', "Lead #{$id} ({$name}) was permanently deleted.");
    }

    /**
     * Bulk delete leads matching specified criteria.
     */
    public function bulkDelete(Request $request)
    {
        $criteria = $request->validate([
            'lead_ids'      => 'sometimes|array',
            'lead_ids.*'    => 'integer',
            'ids'           => 'sometimes|array',
            'ids.*'         => 'integer',
            'email_domain'  => 'sometimes|nullable|string|max:255',
            'email_pattern' => 'sometimes|nullable|string|max:255',
            'emails'        => 'sometimes|nullable|array',
            'emails.*'      => 'string|max:255',
            'status'        => 'sometimes|nullable|string|in:new,reviewed,qualified,rejected',
            'channel'       => 'sometimes|nullable|string|max:50',
            'date_from'     => 'sometimes|nullable|date',
            'date_to'       => 'sometimes|nullable|date',
            'confirm'       => 'sometimes|boolean',
        ]);

        // Filter out null/empty values
        $criteria = array_filter($criteria, fn($val) => $val !== null && $val !== '' && $val !== []);

        if (empty($criteria) && !$request->boolean('confirm')) {
            return redirect()->route('dashboard')->with('error', 'Please specify at least one deletion selector or confirm full wipe.');
        }

        if ($request->boolean('confirm')) {
            $criteria['confirm'] = true;
        }

        $result = $this->bulkService->bulkDelete($criteria);
        $count = $result['deleted_count'];

        return redirect()->route('dashboard')->with('success', "Bulk cleanup complete: {$count} " . ($count === 1 ? 'lead' : 'leads') . " permanently removed.");
    }
}

