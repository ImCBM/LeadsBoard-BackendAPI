<x-layout>
    @push('styles')
    <style>
        thead th a {
            color: inherit;
            text-decoration: none;
            transition: color 0.15s;
        }
        thead th a:hover {
            color: var(--primary);
        }
        .sort-icon {
            font-size: 10px;
            margin-left: 4px;
            opacity: 0.6;
        }
        .sort-icon.active {
            opacity: 1;
            color: var(--primary);
        }
    </style>
    @endpush

    @php
        $currentSortBy = request('sort_by', 'created_at');
        $currentSortDir = request('sort_dir', 'desc');

        $sortUrl = function ($column) use ($currentSortBy, $currentSortDir) {
            $direction = ($currentSortBy === $column && $currentSortDir === 'asc') ? 'desc' : 'asc';
            return request()->fullUrlWithQuery(['sort_by' => $column, 'sort_dir' => $direction]);
        };

        $sortIcon = function ($column) use ($currentSortBy, $currentSortDir) {
            $icon = '↕';
            $class = 'sort-icon';
            if ($currentSortBy === $column) {
                $icon = $currentSortDir === 'asc' ? '↑' : '↓';
                $class .= ' active';
            }
            return "<span class=\"{$class}\">{$icon}</span>";
        };
    @endphp

    <div class="page-header">
        <h1>Leads Dashboard</h1>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('dashboard.export', request()->query()) }}" class="btn btn-outline">
                📥 Export CSV
            </a>
        </div>
    </div>

    {{-- ─── Summary Stats ──────────────────────────────────── --}}
    <div class="stat-cards">
        <div class="stat-card">
            <div class="label">Total Leads</div>
            <div class="value">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Today</div>
            <div class="value">{{ number_format($stats['today']) }}</div>
        </div>
        <div class="stat-card">
            <div class="label">This Week</div>
            <div class="value">{{ number_format($stats['this_week']) }}</div>
        </div>
        <div class="stat-card">
            <div class="label">This Month</div>
            <div class="value">{{ number_format($stats['this_month']) }}</div>
        </div>
    </div>

    {{-- ─── Filter Bar ─────────────────────────────────────── --}}
    <div class="filter-bar">
        <form method="GET" action="{{ route('dashboard') }}">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Name, email, company...">
                </div>

                <div class="filter-group">
                    <label for="industry">Industry</label>
                    <select id="industry" name="industry">
                        <option value="">All Industries</option>
                        @foreach($filterOptions['industries'] as $ind)
                            <option value="{{ $ind }}" {{ request('industry') === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="title_tier">Title Tier</label>
                    <select id="title_tier" name="title_tier">
                        <option value="">All Tiers</option>
                        @foreach($filterOptions['title_tiers'] as $tier)
                            <option value="{{ $tier }}" {{ request('title_tier') === $tier ? 'selected' : '' }}>{{ $tier }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        @foreach($filterOptions['statuses'] as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="country">Country</label>
                    <select id="country" name="country">
                        <option value="">All Countries</option>
                        @foreach($filterOptions['countries'] as $c)
                            <option value="{{ $c }}" {{ request('country') === $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
                </div>

                <div class="filter-group">
                    <label for="date_to">To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline">Clear</a>
                </div>
            </div>
        </form>
    </div>

    {{-- ─── Leads Table ────────────────────────────────────── --}}
    <div class="table-container">
        <div class="table-info">
            <span>Showing {{ $leads->firstItem() ?? 0 }}–{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} leads</span>
            <span>Page {{ $leads->currentPage() }} of {{ $leads->lastPage() }}</span>
        </div>

        @if($leads->isEmpty())
            <div class="empty-state">
                <p>No leads found matching your criteria.</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th><a href="{{ $sortUrl('full_name') }}">Name {!! $sortIcon('full_name') !!}</a></th>
                            <th><a href="{{ $sortUrl('job_title') }}">Job Title {!! $sortIcon('job_title') !!}</a></th>
                            <th><a href="{{ $sortUrl('title_tier') }}">Tier {!! $sortIcon('title_tier') !!}</a></th>
                            <th><a href="{{ $sortUrl('corporate_email') }}">Email {!! $sortIcon('corporate_email') !!}</a></th>
                            <th><a href="{{ $sortUrl('company_name') }}">Company {!! $sortIcon('company_name') !!}</a></th>
                            <th><a href="{{ $sortUrl('industry_classification') }}">Industry {!! $sortIcon('industry_classification') !!}</a></th>
                            <th><a href="{{ $sortUrl('country') }}">Country {!! $sortIcon('country') !!}</a></th>
                            <th><a href="{{ $sortUrl('employee_headcount') }}">Headcount {!! $sortIcon('employee_headcount') !!}</a></th>
                            <th><a href="{{ $sortUrl('status') }}">Status {!! $sortIcon('status') !!}</a></th>
                            <th><a href="{{ $sortUrl('created_at') }}">Added {!! $sortIcon('created_at') !!}</a></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td title="{{ $lead->full_name }}">{{ $lead->full_name }}</td>
                                <td title="{{ $lead->job_title }}">{{ $lead->job_title ?? '—' }}</td>
                                <td>
                                    @php
                                        $tierClass = match($lead->title_tier) {
                                            'C-Level'        => 'chip-c-level',
                                            'VP-Level'       => 'chip-vp-level',
                                            'Director-Level' => 'chip-director',
                                            default          => 'chip-other',
                                        };
                                    @endphp
                                    <span class="chip {{ $tierClass }}">{{ $lead->title_tier }}</span>
                                </td>
                                <td title="{{ $lead->corporate_email }}">
                                    <a href="mailto:{{ $lead->corporate_email }}" style="color: var(--primary); text-decoration: none;">
                                        {{ $lead->corporate_email }}
                                    </a>
                                </td>
                                <td title="{{ $lead->company_name }}">{{ $lead->company_name }}</td>
                                <td title="{{ $lead->industry_classification }}">{{ $lead->industry_classification ?? '—' }}</td>
                                <td>{{ $lead->country ?? '—' }}</td>
                                <td>{{ $lead->employee_headcount ? number_format($lead->employee_headcount) : '—' }}</td>
                                <td>
                                    <span class="chip chip-{{ $lead->status }}">{{ ucfirst($lead->status) }}</span>
                                </td>
                                <td title="{{ $lead->created_at->format('Y-m-d H:i:s') }}">
                                    {{ $lead->created_at->format('M j, Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="pagination-wrapper">
                {{-- Previous --}}
                @if($leads->onFirstPage())
                    <span class="disabled"><span>← Prev</span></span>
                @else
                    <a href="{{ $leads->previousPageUrl() }}">← Prev</a>
                @endif

                {{-- Page Numbers --}}
                @foreach($leads->getUrlRange(max(1, $leads->currentPage() - 2), min($leads->lastPage(), $leads->currentPage() + 2)) as $page => $url)
                    @if($page == $leads->currentPage())
                        <span class="active"><span>{{ $page }}</span></span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach

                {{-- Next --}}
                @if($leads->hasMorePages())
                    <a href="{{ $leads->nextPageUrl() }}">Next →</a>
                @else
                    <span class="disabled"><span>Next →</span></span>
                @endif
            </div>
        @endif
    </div>
</x-layout>
