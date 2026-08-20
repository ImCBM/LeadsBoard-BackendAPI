<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Serves the minimal Blade dashboard views.
 * Uses session-based auth (standard Laravel web auth).
 */
class DashboardController extends Controller
{
    public function __construct(
        private LeadExportService $exportService
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
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
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

        // Get filtered leads
        $leads = Lead::query()
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
            ]))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

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
            'industries' => Lead::whereNotNull('industry_classification')
                                ->distinct()->orderBy('industry_classification')
                                ->pluck('industry_classification'),
            'countries'  => Lead::whereNotNull('country')
                                ->distinct()->orderBy('country')
                                ->pluck('country'),
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
            ->applyFilters($request->only([
                'search', 'industry', 'title_tier', 'status',
                'country', 'ingestion_channel', 'date_from', 'date_to',
            ]));

        $filename = 'leads_export_' . now()->format('Y-m-d_His') . '.csv';

        return $this->exportService->exportCsv($query, $filename);
    }
}
