@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    <div class="container">
        <h4 class="mb-4">Pengaturan Aplikasi</h4>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.store') }}">
            @csrf
            <div class="mb-3">
                <label for="company_name" class="form-label">Nama Perusahaan</label>
                <input type="text" name="company_name" id="company_name" class="form-control"
                    value="{{ $settings['company_name'] ?? '' }}">
            </div>

            <div class="mb-3">
                <label for="timezone" class="form-label">Zona Waktu</label>
                <select name="timezone" id="timezone" class="form-select">
                    @foreach (timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" {{ ($settings['timezone'] ?? '') == $tz ? 'selected' : '' }}>
                            {{ $tz }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </form>
    </div>
@endsection
