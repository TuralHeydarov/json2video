<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — JSON2Video</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"
        media="print" onload="this.media='all'">
    <style>
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
            --border: #1e293b;
            --text: #e2e8f0;
            --text-dim: #94a3b8;
            --text-muted: #64748b;
            --accent: #64ffda;
            --accent-dim: rgba(100, 255, 218, 0.15);
            --blue: #60a5fa;
            --red: #f87171;
            --green: #4ade80;
            --yellow: #fbbf24;
            --gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Navbar */
        .navbar {
            padding: 16px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(10, 14, 26, 0.9);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand .logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            font-size: 12px;
        }

        .navbar-brand h1 {
            font-size: 16px;
            font-weight: 600;
        }

        .navbar-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .navbar-links a {
            color: var(--text-dim);
            font-size: 14px;
        }

        .navbar-links a:hover {
            color: var(--text);
            text-decoration: none;
        }

        .navbar-links a.active {
            color: var(--accent);
        }

        /* Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 32px;
        }

        /* Cards */
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
            padding: 20px;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient);
        }

        .stat-card .label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: 700;
        }

        .stat-card .sub {
            font-size: 12px;
            color: var(--text-dim);
            margin-top: 4px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            padding: 10px 16px;
            text-align: left;
            font-weight: 500;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            font-size: 11px;
            text-transform: uppercase;
        }

        tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
        }

        tbody tr:hover {
            background: var(--bg-card-hover);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
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

        .badge-free {
            background: rgba(100, 255, 218, 0.15);
            color: var(--accent);
        }

        .badge-pending {
            background: rgba(251, 191, 36, 0.15);
            color: var(--yellow);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: inherit;
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
            border: 1px solid var(--border);
            color: var(--text-dim);
        }

        .btn-secondary:hover {
            border-color: var(--text-dim);
            color: var(--text);
            text-decoration: none;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Forms */
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
        input[type="tel"],
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
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 13px;
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

        .alert-info {
            background: rgba(96, 165, 250, 0.1);
            border: 1px solid rgba(96, 165, 250, 0.2);
            color: var(--blue);
        }

        .alert-warning {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.2);
            color: var(--yellow);
        }

        /* Modal */
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
            max-width: 480px;
        }

        .modal h3 {
            margin-bottom: 16px;
            font-size: 18px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .mono {
            font-family: 'SF Mono', Consolas, monospace;
            font-size: 12px;
        }

        .text-muted {
            color: var(--text-muted);
        }

        .text-accent {
            color: var(--accent);
        }

        .text-sm {
            font-size: 12px;
        }

        .mb-3 {
            margin-bottom: 12px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .mt-4 {
            margin-top: 16px;
        }
        @media (max-width: 600px) {
            .navbar { padding: 12px 16px; gap: 12px; flex-wrap: wrap; }
            .navbar-links { width: 100%; gap: 12px; flex-wrap: wrap; }
        }
    </style>
    @yield('styles')
</head>

<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand" style="text-decoration:none">
            <div class="logo">J2V</div>
            <h1>JSON2Video</h1>
        </a>
        <div class="navbar-links">
            @auth
                @if(app(\App\Services\SharedBrowser::class)->available())<a href="/shared/account">Tural account</a>@endif
                <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="/plans" class="{{ request()->is('plans') ? 'active' : '' }}">Plans</a>
                <form method="POST" action="/logout" style="display:inline">@csrf<button type="submit"
                        class="btn btn-sm btn-secondary">Logout</button></form>
            @else
                <a href="/#docs">Docs</a>
                <a href="/#pricing">Pricing</a>
                <a href="/login" class="btn btn-sm btn-secondary">Login</a>
                <a href="/register" class="btn btn-sm btn-primary">Sign Up Free</a>
            @endauth
        </div>
    </nav>

    @if(session('success'))
        <div class="container">
            <div class="alert alert-success">✓ {{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="container">
            <div class="alert alert-error">✕ {{ session('error') }}</div>
        </div>
    @endif

    @yield('content')

    @yield('scripts')
</body>

</html>