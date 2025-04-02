@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
    <div style="max-width: 600px;">
        <h4 class="mb-3">Profil Saya</h4>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            <div class="mb-3">
                <label>Nama</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>
            <button class="btn btn-primary">Simpan Perubahan</button>
        </form>

        <hr>

        <h5 class="mt-4">Ubah Password</h5>
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            <div class="mb-3">
                <label>Password Saat Ini</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password Baru</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="new_password_confirmation" class="form-control" required>
            </div>
            <button class="btn btn-warning">Ubah Password</button>
        </form>
    </div>
@endsection
