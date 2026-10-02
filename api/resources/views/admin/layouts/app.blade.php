<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — JSON2Video</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"
        media="print" onload="this.media='all'">
    <style>
        /* ─── Reset & Base ─────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #0a0e1a;
            --bg-card: #111827;
            --bg-card-hover: #1a2035;
            --bg-sidebar: #0d1117;
            --border: #1e293b;
            --border-light: #2d3a50;
            --text: #e2e8f0;
            --text-dim: #94a3b8;
            --text-muted: #64748b;
            --accent: #64ffda;
            --accent-dim: rgba(100, 255, 218, 0.15);
            --blue: #60a5fa;
            --red: #f87171;
            --green: #4ade80;
            --yellow: #fbbf24;
            --purple: #a78bfa;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* ─── Sidebar ──────────────────────────────── */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            padding: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand .logo {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--gradient-3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            font-size: 14px;
        }

        .sidebar-brand h1 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
        }

        .sidebar-brand span {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-dim);
            font-size: 14px;
            font-weight: 400;
            transition: all 0.2s;
            margin-bottom: 2px;
        }

        .sidebar-nav a:hover {
            background: var(--bg-card);
            color: var(--text);
            text-decoration: none;
        }

        .sidebar-nav a.active {
            background: var(--accent-dim);
            color: var(--accent);
            font-weight: 500;
        }

        .sidebar-nav a .icon {
            font-size: 18px;
            width: 22px;
            text-align: center;
        }

        .sidebar-nav .section-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            padding: 16px 14px 6px;
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .sidebar-footer form button {
            width: 100%;
            padding: 8px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-dim);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }

        .sidebar-footer form button:hover {
            border-color: var(--red);
            color: var(--red);
        }

        /* ─── Main Content ─────────────────────────── */
        .main {
            margin-left: 260px;
            flex: 1;
            min-height: 100vh;
        }

        .topbar {
            padding: 20px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(10, 14, 26, 0.8);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .topbar .breadcrumb {
            font-size: 13px;
            color: var(--text-muted);
        }

        .content {
            padding: 28px 32px;
        }

        /* ─── Cards & Stats ─────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card:nth-child(1)::before {
            background: var(--gradient-3);
        }

        .stat-card:nth-child(2)::before {
            background: var(--gradient-4);
        }

        .stat-card:nth-child(3)::before {
            background: var(--gradient-1);
        }

        .stat-card:nth-child(4)::before {
            background: var(--gradient-2);
        }

        .stat-card .label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card .sub {
            font-size: 12px;
            color: var(--text-dim);
            margin-top: 4px;
        }

        /* ─── Tables ────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h3 {
            font-size: 15px;
            font-weight: 600;
        }

        .card-body {
            padding: 0;
        }

        .card-body.padded {
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 500;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:hover {
            background: var(--bg-card-hover);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* ─── Badges ────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-done {
            background: rgba(74, 222, 128, 0.15);
            color: var(--green);
        }

        .badge-queued {
            background: rgba(251, 191, 36, 0.15);
            color: var(--yellow);
        }

        .badge-processing {
            background: rgba(96, 165, 250, 0.15);
            color: var(--blue);
        }

        .badge-failed {
            background: rgba(248, 113, 113, 0.15);
            color: var(--red);
        }

        .badge-active {
            background: rgba(74, 222, 128, 0.15);
            color: var(--green);
        }

        .badge-inactive {
            background: rgba(100, 116, 139, 0.15);
            color: var(--text-muted);
        }

        /* ─── Buttons ───────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--accent);
            color: #000;
        }

        .btn-primary:hover {
            background: #5ae6c2;
            text-decoration: none;
        }

        .btn-secondary {
            background: transparent;
            border-color: var(--border);
            color: var(--text-dim);
        }

        .btn-secondary:hover {
            border-color: var(--text-dim);
            color: var(--text);
            text-decoration: none;
        }

        .btn-danger {
            background: rgba(248, 113, 113, 0.15);
            color: var(--red);
            border-color: rgba(248, 113, 113, 0.3);
        }

        .btn-danger:hover {
            background: rgba(248, 113, 113, 0.25);
            text-decoration: none;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* ─── Forms ──────────────────────────────────── */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dim);
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }

        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* ─── Alerts ─────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.2);
            color: var(--green);
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.2);
            color: var(--red);
        }

        /* ─── Pagination ─────────────────────────────── */
        .pagination {
            display: flex;
            gap: 4px;
            justify-content: center;
            padding: 16px;
        }

        .pagination a,
        .pagination span {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid var(--border);
            color: var(--text-dim);
        }

        .pagination a:hover {
            background: var(--bg-card-hover);
            text-decoration: none;
        }

        .pagination .active span {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        /* ─── Modal ──────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            width: 90%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal h3 {
            margin-bottom: 20px;
            font-size: 18px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* ─── Utility ────────────────────────────────── */
        .text-muted {
            color: var(--text-muted);
        }

        .text-accent {
            color: var(--accent);
        }

        .text-red {
            color: var(--red);
        }

        .text-sm {
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        .mt-4 {
            margin-top: 16px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .flex {
            display: flex;
        }

        .gap-2 {
            gap: 8px;
        }

        .items-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .mono {
            font-family: 'SF Mono', 'Consolas', monospace;
            font-size: 12px;
        }
    </style>
    @yield('styles')
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo">J2V</div>
            <div>
                <h1>JSON2Video</h1>
                <span>Admin Panel</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="section-label">Overview</div>
            <a href="/admin" class="{{ request()->is('admin') && !request()->is('admin/*') ? 'active' : '' }}">
                <span class="icon">📊</span> Dashboard
            </a>

            <div class="section-label">Management</div>
            <a href="/admin/users" class="{{ request()->is('admin/users*') ? 'active' : '' }}">
                <span class="icon">👥</span> Users
            </a>
            <a href="/admin/plans" class="{{ request()->is('admin/plans*') ? 'active' : '' }}">
                <span class="icon">💎</span> Plans
            </a>
            <a href="/admin/plan-requests" class="{{ request()->is('admin/plan-requests*') ? 'active' : '' }}">
                <span class="icon">📩</span> Plan Requests
            </a>
            <a href="/admin/jobs" class="{{ request()->is('admin/jobs*') ? 'active' : '' }}">
                <span class="icon">🎬</span> Render Jobs
            </a>
            <a href="/admin/transcribe-jobs" class="{{ request()->is('admin/transcribe-jobs*') ? 'active' : '' }}">
                <span class="icon">🎤</span> Transcribe Jobs
            </a>
            <a href="/admin/templates" class="{{ request()->is('admin/templates*') ? 'active' : '' }}">
                <span class="icon">📋</span> Templates
            </a>

            <div class="section-label">Tools</div>
            <a href="/admin/render" class="{{ request()->is('admin/render*') ? 'active' : '' }}">
                <span class="icon">🚀</span> Render Video
            </a>
        </nav>

        <div class="sidebar-footer">
            @if(app(\App\Services\SharedBrowser::class)->available())<a href="/shared/account">Tural account</a>@endif
            <form method="POST" action="/admin/logout">
                @csrf
                <button type="submit">⏻ Logout</button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main">
        <div class="topbar">
            <h2>@yield('title', 'Dashboard')</h2>
            <div class="breadcrumb">@yield('breadcrumb', 'Admin')</div>
        </div>

        <div class="content">
            @if(session('success'))
                <div class="alert alert-success">✓ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">✕ {{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    </div>

    @yield('scripts')
</body>

</html>