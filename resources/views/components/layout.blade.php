<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Leads Dashboard' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1fa97d;
            --on-primary: #ffffff;
            --primary-hover: #106647;
            --primary-container: #c9f1e1;
            --on-primary-container: #08402f;

            --secondary: #e8724a;
            --on-secondary: #ffffff;
            --secondary-hover: #7a2e10;

            --tertiary: #3e93b8;
            --on-tertiary: #ffffff;
            --tertiary-container: #cfebf5;

            --background: #faf9f2;
            --on-background: #1e2a22;

            --surface: #eeeadb;
            --surface-low: #f4f1e6;
            --surface-container: #e7e2d0;
            --surface-hover: #e4dfcc;
            --on-surface: #1e2a22;
            --on-surface-variant: #52584a;

            --outline: #cac5b0;
            --outline-strong: #7e8474;
            --shadow-color: rgba(30, 42, 34, 0.06);
            --shadow-strong: rgba(30, 42, 34, 0.12);

            --error: #c13f2c;
            --error-container: #fad9d0;
            --on-error-container: #6b1b0c;

            --sidebar-width: 240px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: var(--background);
            color: var(--on-background);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 22px;
            display: flex;
            min-height: 100vh;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--on-background);
        }

        /* ─── Sidebar ───────────────────────────────── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface-low);
            border-right: 1px solid var(--outline);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
        }
        .sidebar-brand {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
            padding: 8px 12px;
            margin-bottom: 16px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--on-surface-variant);
            font-weight: 400;
            transition: all 0.15s;
        }
        .sidebar-link:hover {
            background: var(--surface);
            color: var(--on-surface);
        }
        .sidebar-link.active {
            background: var(--primary);
            color: var(--on-primary);
        }
        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--outline);
        }
        .sidebar-user {
            padding: 10px 12px;
            font-size: 13px;
            color: var(--on-surface-variant);
        }
        .sidebar-user strong {
            display: block;
            color: var(--on-surface);
            font-size: 14px;
        }

        /* ─── Main Content ──────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 32px 40px;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* ─── Page Header ───────────────────────────── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .page-header h1 {
            font-size: 28px;
            line-height: 36px;
        }

        /* ─── Buttons ───────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-primary {
            background: var(--primary);
            color: var(--on-primary);
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary {
            background: var(--secondary);
            color: var(--on-secondary);
        }
        .btn-secondary:hover { background: var(--secondary-hover); }
        .btn-outline {
            background: transparent;
            color: var(--on-surface-variant);
            border: 1px solid var(--outline);
        }
        .btn-outline:hover {
            background: var(--surface);
            color: var(--on-surface);
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .btn-danger {
            background: var(--error);
            color: white;
        }
        .btn-danger:hover { opacity: 0.9; }

        /* ─── Cards ──────────────────────────────────── */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface-low);
            border: 1px solid var(--outline);
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px var(--shadow-color);
        }
        .stat-card .label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--on-surface-variant);
            margin-bottom: 4px;
        }
        .stat-card .value {
            font-family: 'Poppins', sans-serif;
            font-size: 32px;
            font-weight: 600;
            color: var(--on-surface);
        }
        .stat-card:nth-child(1) .value { color: var(--primary); }
        .stat-card:nth-child(2) .value { color: var(--secondary); }
        .stat-card:nth-child(3) .value { color: var(--tertiary); }

        /* ─── Filters ────────────────────────────────── */
        .filter-bar {
            background: var(--surface-low);
            border: 1px solid var(--outline);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px var(--shadow-color);
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--on-surface-variant);
        }
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--outline);
            border-radius: 8px;
            background: var(--background);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: var(--on-surface);
            min-width: 160px;
            transition: border-color 0.15s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-container);
        }
        .filter-group input[type="text"] { min-width: 240px; }
        .filter-group input[type="date"] { min-width: 140px; }
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }

        /* ─── Data Table ─────────────────────────────── */
        .table-container {
            background: var(--surface-low);
            border: 1px solid var(--outline);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow-color);
        }
        .table-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--outline);
            font-size: 13px;
            color: var(--on-surface-variant);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--on-surface-variant);
            background: var(--surface);
            border-bottom: 1px solid var(--outline);
            white-space: nowrap;
        }
        tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--outline);
            font-size: 14px;
            color: var(--on-surface);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        tbody tr:hover {
            background: var(--surface-hover);
        }
        tbody tr:last-child td {
            border-bottom: none;
        }

        /* ─── Status & Tier Chips ────────────────────── */
        .chip {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .chip-new        { background: var(--primary-container); color: var(--on-primary-container); }
        .chip-reviewed   { background: var(--tertiary-container); color: #0f3c4c; }
        .chip-qualified  { background: #d4edda; color: #155724; }
        .chip-rejected   { background: var(--error-container); color: var(--on-error-container); }
        .chip-c-level    { background: #ffe0b2; color: #e65100; }
        .chip-vp-level   { background: #e1bee7; color: #6a1b9a; }
        .chip-director   { background: #b3e5fc; color: #01579b; }
        .chip-other      { background: var(--surface); color: var(--on-surface-variant); }

        /* ─── Pagination ─────────────────────────────── */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            padding: 16px 20px;
            border-top: 1px solid var(--outline);
        }
        .pagination-wrapper a,
        .pagination-wrapper span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--on-surface-variant);
            transition: all 0.15s;
        }
        .pagination-wrapper a:hover {
            background: var(--surface);
            color: var(--on-surface);
        }
        .pagination-wrapper .active span {
            background: var(--primary);
            color: var(--on-primary);
        }
        .pagination-wrapper .disabled span {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ─── Alert / Flash Messages ─────────────────── */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: var(--error-container);
            color: var(--on-error-container);
            border: 1px solid #f5c6cb;
        }

        /* ─── Empty State ────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--on-surface-variant);
        }
        .empty-state p { font-size: 16px; }

        /* ─── Responsive ─────────────────────────────── */
        @media (max-width: 1024px) {
            .sidebar { width: 60px; padding: 16px 8px; }
            .sidebar-brand { font-size: 0; padding: 8px; }
            .sidebar-brand::first-letter { font-size: 20px; }
            .sidebar-link span { display: none; }
            .sidebar-user { display: none; }
            .main-content { margin-left: 60px; padding: 20px; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 16px; }
            .stat-cards { grid-template-columns: 1fr 1fr; }
            .filter-row { flex-direction: column; }
            .filter-group input,
            .filter-group select { min-width: 100%; }
            .page-header { flex-direction: column; gap: 12px; align-items: flex-start; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @auth
    <nav class="sidebar">
        <div class="sidebar-brand">LeadsBoard</div>
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span>📊</span> <span>Dashboard</span>
        </a>
        <a href="{{ route('dashboard.api-keys.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.api-keys.*') ? 'active' : '' }}">
            <span>🔑</span> <span>API Keys</span>
        </a>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <strong>{{ Auth::user()->name }}</strong>
                {{ Auth::user()->email }}
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link" style="width: 100%; border: none; background: none; cursor: pointer; text-align: left;">
                    <span>🚪</span> <span>Logout</span>
                </button>
            </form>
        </div>
    </nav>
    @endauth

    <div class="main-content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </div>

    @stack('scripts')
</body>
</html>
