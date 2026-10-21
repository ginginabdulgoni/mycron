@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 1.15rem;
            padding: 2rem;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 24px -6px rgba(79, 70, 229, 0.35);
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            right: -40px;
            bottom: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .stat-icon.success {
            background: #dcfce7;
            color: #15803d;
        }

        .stat-icon.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .stat-icon.warning {
            background: #fef3c7;
            color: #b45309;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
            margin: 0.75rem 0 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
        }

        .quick-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            text-decoration: none;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .quick-card:hover {
            border-color: #4f46e5;
            background: #f8fafc;
            color: #4f46e5;
            transform: translateY(-2px);
        }

        .quick-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 0.75rem;
            background: #eef2ff;
            color: #4f46e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
    </style>

    {{-- Welcome Banner --}}
    <div class="welcome-banner mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index: 1;">
            <div>
                <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-1 mb-2 fw-semibold">
                    <i class="bi bi-stars me-1"></i>{{ auth()->user()->role === 'admin' ? 'Administrator Mode' : 'User Panel' }}
                </span>
                <h2 class="fw-bold mb-1">Halo, {{ auth()->user()->name }}!</h2>
                <p class="mb-0 text-white text-opacity-75">
                    Selamat datang di pusat kendali penjadwalan otomatis {{ setting('company_name', 'MyCron') }}.
                </p>
            </div>
            <div>
                <a href="{{ route('cronjobs.index') }}" class="btn btn-light fw-semibold text-primary shadow-sm">
                    <i class="bi bi-alarm me-1"></i>Kelola Cronjob
                </a>
            </div>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Cron Aktif</div>
                        <div class="stat-value">{{ $totalActive }}</div>
                    </div>
                    <div class="stat-icon success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small">
                    <span class="text-success fw-medium"><i class="bi bi-activity me-1"></i>Berjalan terjadwal</span>
                    <a href="{{ route('cronjobs.index') }}" class="text-decoration-none fw-semibold text-muted">Lihat <i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Cron Nonaktif</div>
                        <div class="stat-value">{{ $totalInactive }}</div>
                    </div>
                    <div class="stat-icon danger">
                        <i class="bi bi-pause-circle-fill"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small">
                    <span class="text-muted">Dijeda sementara</span>
                    <a href="{{ route('cronjobs.index') }}" class="text-decoration-none fw-semibold text-muted">Lihat <i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Domain RTO (Timeout)</div>
                        <div class="stat-value">{{ $totalRtoDomains }}</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between small">
                    <span class="{{ $totalRtoDomains > 0 ? 'text-warning fw-medium' : 'text-success fw-medium' }}">
                        {{ $totalRtoDomains > 0 ? 'Perlu perhatian' : 'Semua domain sehat' }}
                    </span>
                    <a href="{{ route('rto-domains.index') }}" class="text-decoration-none fw-semibold text-primary">Lihat Detail <i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mb-2">
        <h6 class="text-uppercase fw-bold text-muted small mb-3" style="letter-spacing: 0.05em;">Akses Cepat</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <a href="{{ route('cronjobs.index') }}" class="quick-card">
                    <div class="quick-card-icon"><i class="bi bi-plus-circle-fill"></i></div>
                    <div>
                        <div class="fw-bold">Manajemen Cronjob</div>
                        <div class="text-muted small">Tambah, ubah, atau pantau jadwal</div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('rto-domains.index') }}" class="quick-card">
                    <div class="quick-card-icon"><i class="bi bi-shield-exclamation"></i></div>
                    <div>
                        <div class="fw-bold">Monitor Domain RTO</div>
                        <div class="text-muted small">Cek domain yang mengalami timeout</div>
                    </div>
                </a>
            </div>
            @can('admin')
                <div class="col-md-4">
                    <a href="{{ route('system-update.index') }}" class="quick-card">
                        <div class="quick-card-icon"><i class="bi bi-cpu-fill"></i></div>
                        <div>
                            <div class="fw-bold">Status Worker & Update</div>
                            <div class="text-muted small">Pantau worker dan versi aplikasi</div>
                        </div>
                    </a>
                </div>
            @else
                <div class="col-md-4">
                    <a href="{{ route('profile.edit') }}" class="quick-card">
                        <div class="quick-card-icon"><i class="bi bi-person-badge-fill"></i></div>
                        <div>
                            <div class="fw-bold">Pengaturan Profil</div>
                            <div class="text-muted small">Perbarui informasi akun & password</div>
                        </div>
                    </a>
                </div>
            @endcan
        </div>
    </div>
@endsection
