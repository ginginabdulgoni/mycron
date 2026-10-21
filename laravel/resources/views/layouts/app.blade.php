<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Dashboard') &mdash; {{ setting('company_name', 'MyCron') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 68px;
            --brand-primary: #4f46e5;
            --brand-secondary: #7c3aed;
            --bg-body: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            padding-top: var(--topbar-height);
            overflow-x: hidden;
        }

        code, pre, .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Top Navbar */
        .app-topbar {
            height: var(--topbar-height);
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            z-index: 1020;
            padding: 0 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: left 0.25s ease;
        }

        /* Sidebar */
        .app-sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            background: #0f172a;
            color: #94a3b8;
            z-index: 1030;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
        }

        .sidebar-brand {
            height: var(--topbar-height);
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
            text-decoration: none;
            color: #fff;
        }

        .sidebar-brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 0.65rem;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.15rem;
            box-shadow: 0 6px 14px -3px rgba(79, 70, 229, 0.55);
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.05rem;
            color: #fff;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .sidebar-brand-sub {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            display: block;
        }

        .sidebar-nav {
            padding: 1.25rem 0.875rem;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-section-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            padding: 0 0.75rem;
            margin: 1rem 0 0.45rem;
        }

        .sidebar-section-label:first-child {
            margin-top: 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.65rem;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
            transition: all 0.18s ease;
        }

        .sidebar-link i {
            font-size: 1.1rem;
            width: 22px;
            text-align: center;
            transition: transform 0.18s ease;
        }

        .sidebar-link:hover {
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.06);
        }

        .sidebar-link.active {
            color: #fff;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            box-shadow: 0 8px 16px -4px rgba(79, 70, 229, 0.45);
            font-weight: 600;
        }

        /* Main Content & Footer */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.85rem 2rem;
            flex: 1;
            transition: margin-left 0.25s ease;
        }

        .app-footer {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            background: #fff;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.825rem;
            transition: margin-left 0.25s ease;
        }

        /* Clock Pill */
        .clock-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 999px;
            padding: 0.4rem 0.9rem;
            font-size: 0.8rem;
            color: #475569;
            font-weight: 500;
        }

        .clock-pill i {
            color: var(--brand-primary);
        }

        /* User Dropdown Button */
        .user-dropdown-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 999px;
            padding: 0.3rem 0.85rem 0.3rem 0.35rem;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .user-dropdown-btn:hover, .user-dropdown-btn:focus {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Modern Cards & Components */
        .card-modern {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03), 0 4px 12px rgba(15, 23, 42, 0.02);
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03), 0 4px 12px rgba(15, 23, 42, 0.02);
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-weight: 700;
            font-size: 1.45rem;
            color: var(--text-main);
            margin-bottom: 0.15rem;
            letter-spacing: -0.02em;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        /* Buttons */
        .btn {
            border-radius: 0.65rem;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            transition: all 0.18s ease;
        }

        .btn-sm {
            border-radius: 0.5rem;
            padding: 0.35rem 0.7rem;
            font-size: 0.78rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            border: none;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
            background: linear-gradient(135deg, #4338ca, #6d28d9);
        }

        /* Soft Badges */
        .badge-soft-success {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 600;
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-soft-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-soft-warning {
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 600;
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-soft-info {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-soft-primary {
            background-color: #e0e7ff;
            color: #4338ca;
            font-weight: 600;
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge-soft-secondary {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Tables */
        .table {
            vertical-align: middle;
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 1rem;
        }

        .table tbody td {
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        .table-striped>tbody>tr:nth-of-type(odd)>* {
            --bs-table-bg-type: rgba(248, 250, 252, 0.5);
        }

        /* Forms & Modals */
        .form-control, .form-select {
            border-radius: 0.65rem;
            border: 1.5px solid #e2e8f0;
            padding: 0.55rem 0.9rem;
            font-size: 0.9rem;
            color: var(--text-main);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.825rem;
            color: #334155;
            margin-bottom: 0.35rem;
        }

        .modal-content {
            border: none;
            border-radius: 1.15rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
        }

        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 1rem 1.5rem;
        }

        /* Mobile Responsive */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            z-index: 1025;
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                transform: translateX(-100%);
            }

            .app-sidebar.show {
                transform: translateX(0);
            }

            .sidebar-backdrop.show {
                display: block;
            }

            .app-topbar, .main-content, .app-footer {
                left: 0;
                margin-left: 0;
            }

            .main-content {
                padding: 1.25rem 1rem;
            }
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    @php($navbarTimezone = setting('timezone', config('app.timezone', 'UTC')))

    {{-- Mobile Backdrop --}}
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    {{-- Sidebar --}}
    <aside class="app-sidebar" id="appSidebar">
        <a href="/dashboard" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <span class="sidebar-brand-text">{{ setting('company_name', 'MyCron') }}</span>
                <span class="sidebar-brand-sub">Task Scheduler</span>
            </div>
        </a>

        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Menu Utama</div>
            <a class="sidebar-link {{ request()->is('dashboard*') ? 'active' : '' }}" href="/dashboard">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
            <a class="sidebar-link {{ request()->is('cronjobs*') || request()->is('cronlogs*') ? 'active' : '' }}" href="/cronjobs">
                <i class="bi bi-alarm-fill"></i>
                <span>Manajemen Cronjob</span>
            </a>
            <a class="sidebar-link {{ request()->is('rto-domains*') ? 'active' : '' }}" href="/rto-domains">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Domain RTO</span>
            </a>

            @can('admin')
                <div class="sidebar-section-label">Administrator</div>
                <a class="sidebar-link {{ request()->is('apikeys*') ? 'active' : '' }}" href="/apikeys">
                    <i class="bi bi-key-fill"></i>
                    <span>API Keys</span>
                </a>
                <a class="sidebar-link {{ request()->is('users*') ? 'active' : '' }}" href="/users">
                    <i class="bi bi-people-fill"></i>
                    <span>Manajemen User</span>
                </a>
                <a class="sidebar-link {{ request()->is('settings*') ? 'active' : '' }}" href="/settings">
                    <i class="bi bi-sliders"></i>
                    <span>Pengaturan</span>
                </a>
                <a class="sidebar-link {{ request()->is('system-update*') ? 'active' : '' }}" href="/system-update">
                    <i class="bi bi-cloud-arrow-down-fill"></i>
                    <span>Update System</span>
                </a>
            @endcan
        </nav>
    </aside>

    {{-- Top Navbar --}}
    <header class="app-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none border p-2" type="button" onclick="toggleSidebar()" aria-label="Menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="clock-pill">
                <i class="bi bi-globe2"></i>
                <span class="d-none d-sm-inline">Zona Waktu (<span id="current-tz" class="fw-semibold">{{ $navbarTimezone }}</span>):</span>
                <span id="current-time" class="font-mono fw-semibold text-dark"></span>
            </div>
        </div>

        <div class="dropdown">
            <button class="user-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-avatar">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </span>
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                <span class="badge-soft-primary text-uppercase d-none d-sm-inline" style="font-size: 0.65rem;">
                    {{ auth()->user()->role }}
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2 py-2" style="min-width: 200px;">
                <li class="px-3 py-1 border-bottom mb-1">
                    <div class="fw-bold small text-dark">{{ auth()->user()->name }}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">{{ auth()->user()->email }}</div>
                </li>
                <li>
                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person-gear text-primary"></i>
                        <span>Profil Saya</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Keluar</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Main Content --}}
    <main class="main-content">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="app-footer d-flex flex-wrap justify-content-between align-items-center gap-2 mt-auto">
        <div>
            <span class="fw-semibold text-dark">{{ setting('company_name', 'MyCron') }}</span> &copy; {{ date('Y') }} &mdash; Sebelas Dua Belas Project
        </div>
        <div class="text-muted small">
            <i class="bi bi-shield-check text-success me-1"></i>Automated Cron Runner
        </div>
    </footer>

    <script>
        function toggleSidebar() {
            document.getElementById('appSidebar').classList.toggle('show');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
        }

        const appTimezone = @json($navbarTimezone);
        const timeFormatter = new Intl.DateTimeFormat('id-ID', {
            timeZone: appTimezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        function updateTime() {
            const timeEl = document.getElementById('current-time');
            const tzEl = document.getElementById('current-tz');
            if (timeEl) timeEl.innerText = timeFormatter.format(new Date());
            if (tzEl) tzEl.innerText = appTimezone;
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>

</html>
