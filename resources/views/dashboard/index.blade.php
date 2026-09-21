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

        /* ─── Row & Action Styles ─── */
        .btn-icon-danger {
            background: none;
            border: 1px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            padding: 4px 6px;
            font-size: 13px;
            transition: all 0.15s;
        }
        .btn-icon-danger:hover {
            background: #fad9d0;
            border-color: #f3bcaf;
        }
        .btn-danger {
            background-color: var(--error, #c13f2c);
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn-danger:hover:not(:disabled) {
            background-color: #a43220;
        }
        .btn-danger:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ─── Floating Batch Bar ─── */
        .batch-bar {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--on-surface, #1e2a22);
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 999px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 500;
            font-size: 13px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .batch-badge {
            background: var(--primary, #1fa97d);
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            margin-right: 4px;
        }
        .btn-link-light {
            background: none;
            border: none;
            color: #dfd9c4;
            font-size: 12px;
            cursor: pointer;
            padding: 4px 8px;
            text-decoration: underline;
        }
        .btn-link-light:hover {
            color: #ffffff;
        }

        /* ─── Modals ─── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(30, 42, 34, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }
        .modal-dialog {
            background: #ffffff;
            border-radius: 14px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: 1px solid var(--outline, #cac5b0);
        }
        .modal-dialog.modal-lg {
            max-width: 620px;
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eee8d7;
            background: #fdfaf7;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 16px;
            color: var(--on-surface, #1e2a22);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--on-surface-variant);
        }
        .modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 75vh;
            overflow-y: auto;
        }
        .modal-footer {
            padding: 14px 20px;
            background: #f4f1e6;
            border-top: 1px solid #eee8d7;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* ─── Info & Confirmation Elements ─── */
        .card-preview {
            background: #f8f6f0;
            border: 1px solid #dfd9c4;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
        }
        .safe-notice {
            background: #f3f7f4;
            border: 1px solid #d4e7da;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12px;
            color: #1e452a;
            line-height: 1.45;
        }
        .confirm-box {
            background: #fdfaf7;
            border: 1px dashed var(--error, #c13f2c);
            border-radius: 8px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .confirm-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--outline, #cac5b0);
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }
        .confirm-input:focus {
            border-color: var(--error, #c13f2c);
        }

        /* ─── Tabs in Cleanup Modal ─── */
        .tab-bar {
            display: flex;
            border-bottom: 1px solid #eee8d7;
            background: #fdfaf7;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            font-size: 12px;
            font-weight: 500;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            color: var(--on-surface-variant);
            text-align: center;
        }
        .tab-btn.active {
            color: var(--error, #c13f2c);
            border-bottom-color: var(--error, #c13f2c);
            font-weight: 600;
        }
        .tab-content {
            display: none;
            flex-direction: column;
            gap: 12px;
        }
        .tab-content.active {
            display: flex;
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
            <button type="button" class="btn btn-outline" style="color: var(--error); border-color: #f3bcaf;" onclick="openCleanupModal()">
                🧹 Cleanup Leads
            </button>
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

                <div class="filter-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if(request()->hasAny(['search', 'industry', 'title_tier', 'status', 'country', 'date_from', 'date_to']))
                        <a href="{{ route('dashboard') }}" class="btn btn-outline" style="margin-left: 4px;">Reset</a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ─── Leads Table ────────────────────────────────────── --}}
    <div class="card">
        @if($leads->isEmpty())
            <div class="empty-state">
                <p>No leads found matching the criteria.</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 38px; text-align: center;">
                                <input type="checkbox" id="selectAllCheckbox" style="accent-color: var(--primary); cursor: pointer;" title="Select all on this page">
                            </th>
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
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td style="text-align: center;" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="lead-row-check" value="{{ $lead->id }}" style="accent-color: var(--primary); cursor: pointer;">
                                </td>
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
                                <td style="text-align: right;" onclick="event.stopPropagation();">
                                    <button type="button" class="btn-icon-danger" title="Delete lead" onclick="openSingleDeleteModal({{ $lead->id }}, '{{ addslashes($lead->full_name) }}', '{{ addslashes($lead->corporate_email) }}', '{{ addslashes($lead->company_name) }}')">
                                        🗑️
                                    </button>
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

    {{-- ─── Floating Batch Action Bar ─── --}}
    <div id="batchActionBar" class="batch-bar" style="display: none;">
        <div>
            <span id="batchCountBadge" class="batch-badge">0</span>
            <span id="batchCountText">leads selected</span>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn-link-light" onclick="deselectAllLeads()">Deselect All</button>
            <button type="button" class="btn btn-danger" onclick="openBulkDeleteModal()">
                🗑️ Delete Selected
            </button>
        </div>
    </div>

    {{-- ─── Modal 1: Single Lead Delete ─── --}}
    <div id="singleDeleteModal" class="modal-backdrop" style="display: none;" onclick="closeModal('singleDeleteModal')">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <form id="singleDeleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3>🗑️ Delete Lead</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('singleDeleteModal')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="card-preview">
                        <strong id="singleDeleteName" style="font-size: 14px; display: block; margin-bottom: 4px;"></strong>
                        <div style="color: var(--on-surface-variant); font-size: 12px;" id="singleDeleteMeta"></div>
                    </div>
                    <p style="margin: 0; font-size: 13px; line-height: 1.45;">
                        This person will be permanently removed from your pipeline. There is no trash bin or undo button.
                    </p>
                    <div class="safe-notice">
                        <strong>Company data is safe:</strong> Company profiles, website domains, and industry categories will remain saved in your database so other records aren't affected.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('singleDeleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Permanently Delete Lead</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 2: Bulk Delete Selected Leads ─── --}}
    <div id="bulkDeleteModal" class="modal-backdrop" style="display: none;" onclick="closeModal('bulkDeleteModal')">
        <div class="modal-dialog" onclick="event.stopPropagation();">
            <form method="POST" action="{{ route('dashboard.leads.bulkDelete') }}">
                @csrf
                <div id="bulkDeleteHiddenInputs"></div>
                <div class="modal-header">
                    <h3>🗑️ Delete Selected Leads</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('bulkDeleteModal')">✕</button>
                </div>
                <div class="modal-body">
                    <p style="margin: 0; font-size: 13px; line-height: 1.45;">
                        You are about to permanently delete <strong id="bulkDeleteCountText"></strong> selected leads from your pipeline.
                    </p>
                    <div class="safe-notice">
                        <strong>Company data is safe:</strong> Company profiles, root domains, and industry categories will remain saved in your database.
                    </div>
                    <div class="confirm-box">
                        <label for="bulkConfirmInput" style="font-size: 12px; font-weight: 600;">
                            To confirm, type <strong>DELETE</strong> below:
                        </label>
                        <input type="text" id="bulkConfirmInput" class="confirm-input" placeholder="Type DELETE to confirm" oninput="checkBulkConfirm()">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('bulkDeleteModal')">Cancel</button>
                    <button type="submit" id="bulkSubmitBtn" class="btn btn-danger" disabled>Permanently Delete Leads</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 3: Cleanup Operations Modal ─── --}}
    <div id="cleanupModal" class="modal-backdrop" style="display: none;" onclick="closeModal('cleanupModal')">
        <div class="modal-dialog modal-lg" onclick="event.stopPropagation();">
            <form method="POST" action="{{ route('dashboard.leads.bulkDelete') }}" id="cleanupForm">
                @csrf
                <div class="modal-header">
                    <h3>🧹 Lead Cleanup Operations</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('cleanupModal')">✕</button>
                </div>

                {{-- Tabs --}}
                <div class="tab-bar">
                    <button type="button" class="tab-btn active" onclick="switchCleanupTab('tabFilters')">Active Filters</button>
                    <button type="button" class="tab-btn" onclick="switchCleanupTab('tabDomain')">Domain & Pattern</button>
                    <button type="button" class="tab-btn" onclick="switchCleanupTab('tabStatus')">Status & Date</button>
                    <button type="button" class="tab-btn" onclick="switchCleanupTab('tabWipe')">Reset Database</button>
                </div>

                <div class="modal-body">
                    {{-- Tab 1: Current Filters --}}
                    <div id="tabFilters" class="tab-content active">
                        <p style="margin: 0; font-size: 13px;">
                            Delete all leads matching your current active pipeline filters:
                        </p>
                        <div class="card-preview">
                            <span style="font-weight: 600; color: #8c3b0d;">Current Filter Parameters:</span>
                            <div style="margin-top: 4px; font-size: 12px; color: #52584a;">
                                Search: {{ request('search') ?: 'None' }} |
                                Industry: {{ request('industry') ?: 'All' }} |
                                Tier: {{ request('title_tier') ?: 'All' }} |
                                Status: {{ request('status') ?: 'All' }} |
                                Country: {{ request('country') ?: 'All' }}
                            </div>
                        </div>
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="industry" value="{{ request('industry') }}">
                        <input type="hidden" name="title_tier" value="{{ request('title_tier') }}">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <input type="hidden" name="country" value="{{ request('country') }}">
                    </div>

                    {{-- Tab 2: Domain & Wildcard --}}
                    <div id="tabDomain" class="tab-content">
                        <label style="font-size: 12px; font-weight: 600;">Exact Email Domain</label>
                        <input type="text" name="email_domain" class="confirm-input" placeholder="e.g. acmecorp.com">
                        <small style="color: #52584a; font-size: 11px;">Target leads matching this domain (e.g. for GDPR removal).</small>

                        <label style="font-size: 12px; font-weight: 600; margin-top: 6px;">Or SQL Wildcard Pattern</label>
                        <input type="text" name="email_pattern" class="confirm-input" placeholder="e.g. %@testleads.%">
                        <small style="color: #52584a; font-size: 11px;">Matches pattern across email addresses.</small>
                    </div>

                    {{-- Tab 3: Status & Date --}}
                    <div id="tabStatus" class="tab-content">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="font-size: 12px; font-weight: 600;">Status</label>
                                <select name="status" class="confirm-input">
                                    <option value="">Any Status</option>
                                    <option value="rejected" selected>Rejected</option>
                                    <option value="reviewed">Reviewed</option>
                                    <option value="qualified">Qualified</option>
                                    <option value="new">New</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 600;">Channel</label>
                                <select name="channel" class="confirm-input">
                                    <option value="">Any Channel</option>
                                    <option value="n8n">n8n</option>
                                    <option value="csv_upload">CSV Upload</option>
                                    <option value="api">API</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="font-size: 12px; font-weight: 600;">Created After</label>
                                <input type="date" name="date_from" class="confirm-input">
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 600;">Created Before</label>
                                <input type="date" name="date_to" class="confirm-input">
                            </div>
                        </div>
                    </div>

                    {{-- Tab 4: Reset Database --}}
                    <div id="tabWipe" class="tab-content">
                        <div style="background: #fad9d0; border: 1px solid #f3bcaf; border-radius: 8px; padding: 12px; color: #6b1b0c; font-size: 13px;">
                            ⚠️ <strong>Full Database Reset:</strong> This will permanently delete every lead in your pipeline. Company profiles, website domains, and categories will remain saved in your database so other records aren't lost.
                        </div>
                        <input type="hidden" id="wipeConfirmField" name="confirm" value="0">
                    </div>

                    <div class="safe-notice">
                        <strong>Company data is safe:</strong> Company profiles, website domains, and industry categories will remain saved in your database so other records aren't affected.
                    </div>

                    <div class="confirm-box">
                        <label for="cleanupConfirmInput" style="font-size: 12px; font-weight: 600;">
                            Type <strong id="cleanupRequiredWord">DELETE</strong> below to confirm:
                        </label>
                        <input type="text" id="cleanupConfirmInput" class="confirm-input" placeholder="Type confirmation keyword" oninput="checkCleanupConfirm()">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('cleanupModal')">Cancel</button>
                    <button type="submit" id="cleanupSubmitBtn" class="btn btn-danger" disabled>Execute Cleanup</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        // Checkbox & Batch selection
        const selectAll = document.getElementById('selectAllCheckbox');
        const rowChecks = document.querySelectorAll('.lead-row-check');
        const batchBar = document.getElementById('batchActionBar');
        const batchBadge = document.getElementById('batchCountBadge');
        const batchCountText = document.getElementById('batchCountText');

        function updateBatchState() {
            const checked = document.querySelectorAll('.lead-row-check:checked');
            const count = checked.length;
            if (count > 0) {
                batchBar.style.display = 'flex';
                batchBadge.innerText = count;
                batchCountText.innerText = count === 1 ? 'lead selected' : 'leads selected';
            } else {
                batchBar.style.display = 'none';
            }

            if (selectAll) {
                selectAll.checked = count > 0 && count === rowChecks.length;
                selectAll.indeterminate = count > 0 && count < rowChecks.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowChecks.forEach(cb => cb.checked = selectAll.checked);
                updateBatchState();
            });
        }

        rowChecks.forEach(cb => {
            cb.addEventListener('change', updateBatchState);
        });

        function deselectAllLeads() {
            rowChecks.forEach(cb => cb.checked = false);
            if (selectAll) selectAll.checked = false;
            updateBatchState();
        }

        // Modal Helpers
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openSingleDeleteModal(id, name, email, company) {
            const form = document.getElementById('singleDeleteForm');
            form.action = '/dashboard/leads/' + id;
            document.getElementById('singleDeleteName').innerText = name;
            document.getElementById('singleDeleteMeta').innerText = (company ? company + ' · ' : '') + email;
            document.getElementById('singleDeleteModal').style.display = 'flex';
        }

        function openBulkDeleteModal() {
            const checked = document.querySelectorAll('.lead-row-check:checked');
            if (checked.length === 0) return;

            const container = document.getElementById('bulkDeleteHiddenInputs');
            container.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'lead_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });

            document.getElementById('bulkDeleteCountText').innerText = checked.length;
            document.getElementById('bulkConfirmInput').value = '';
            document.getElementById('bulkSubmitBtn').disabled = true;
            document.getElementById('bulkDeleteModal').style.display = 'flex';
        }

        function checkBulkConfirm() {
            const val = document.getElementById('bulkConfirmInput').value.trim().toUpperCase();
            document.getElementById('bulkSubmitBtn').disabled = (val !== 'DELETE');
        }

        // Cleanup Modal Tab Switcher
        let currentCleanupTab = 'tabFilters';
        function switchCleanupTab(tabId) {
            currentCleanupTab = tabId;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');

            const requiredWord = tabId === 'tabWipe' ? 'WIPE ALL LEADS' : 'DELETE';
            document.getElementById('cleanupRequiredWord').innerText = requiredWord;
            document.getElementById('cleanupConfirmInput').placeholder = 'Type ' + requiredWord + ' to confirm';
            document.getElementById('cleanupConfirmInput').value = '';

            const wipeField = document.getElementById('wipeConfirmField');
            if (wipeField) {
                wipeField.value = (tabId === 'tabWipe') ? '1' : '0';
            }

            checkCleanupConfirm();
        }

        function openCleanupModal() {
            document.getElementById('cleanupConfirmInput').value = '';
            document.getElementById('cleanupSubmitBtn').disabled = true;
            document.getElementById('cleanupModal').style.display = 'flex';
        }

        function checkCleanupConfirm() {
            const val = document.getElementById('cleanupConfirmInput').value.trim().toUpperCase();
            const target = currentCleanupTab === 'tabWipe' ? 'WIPE ALL LEADS' : 'DELETE';
            document.getElementById('cleanupSubmitBtn').disabled = (val !== target);
        }
    </script>
    @endpush
</x-layout>
