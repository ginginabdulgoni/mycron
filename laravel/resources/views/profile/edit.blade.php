@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Profil Saya</h1>
            <p class="page-subtitle">Kelola informasi akun pribadi dan keamanan kata sandi Anda</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="row g-4">
        {{-- Informasi Profil --}}
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <span class="user-avatar" style="width: 48px; height: 48px; font-size: 1.2rem;">
                        {{ substr($user->name, 0, 1) }}
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0">Informasi Akun</h5>
                        <span class="text-muted small">Perbarui nama dan alamat email login Anda</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="email">Alamat Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <button class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        {{-- Ubah Password --}}
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.3rem;">
                        <i class="bi bi-key-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Keamanan Password</h5>
                        <span class="text-muted small">Gunakan kombinasi password yang kuat untuk melindungi akun</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Password Saat Ini</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">Password Baru</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="new_password_confirmation">Konfirmasi Password Baru</label>
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-control" required>
                    </div>
                    <button class="btn btn-warning text-dark">
                        <i class="bi bi-shield-lock me-1"></i>Perbarui Password
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
