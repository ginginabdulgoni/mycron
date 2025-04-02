<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 56px;
            /* tinggi navbar */
        }

        .sidebar {
            width: 200px;
            position: fixed;
            top: 56px;
            left: 0;
            bottom: 0;
            background-color: #f8f9fa;
            padding-top: 20px;
        }

        .main-content {
            margin-left: 200px;
            padding: 20px;
        }

        footer {
            margin-left: 200px;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    {{-- Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container-fluid d-flex justify-content-between align-items-center">

            <div class="d-flex align-items-center">
                <span class="fw-bold me-4">
                    {{ setting('company_name', 'My Cronjob') }}
                </span>

                <span class="text-muted small">
                    Waktu: <span id="current-time"></span>
                </span>
            </div>

            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    {{ auth()->user()->name }} ({{ auth()->user()->role }})
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profil Saya</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>


        </div>
    </nav>


    {{-- Sidebar --}}
    <div class="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="/cronjobs">Cronjob</a></li>

            @can('admin')
                <li class="nav-item"><a class="nav-link" href="/apikeys">API Keys</a></li>
                <li class="nav-item"><a class="nav-link" href="/settings">Settings</a></li>
            @endcan

        </ul>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    {{-- Main Content --}}
    <main class="main-content">
        @yield('content')
    </main>

    {{-- Footer (sticky) --}}
    <footer class="bg-light text-center py-3 border-top mt-auto">
        <div>MyCron &copy; {{ date('Y') }} - Sebelas Dua Belas Project</div>
    </footer>

    <script>
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').innerText = now.toLocaleString();
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
    <!-- Scripts -->

</body>


</html>
