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

            <!-- ✅ Tambahkan opsi clear logs -->
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="clear_logs_active" id="clear_logs_active" class="form-check-input"
                        {{ isset($settings['clear_logs_active']) && $settings['clear_logs_active'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="clear_logs_active">Aktifkan Jadwal Pembersihan Log</label>
                </div>
            </div>

            <div class="mb-3">
                <label for="clear_logs_schedule" class="form-label">Jadwal Pembersihan Log</label>
                <select name="clear_logs_schedule" id="clear_logs_schedule" class="form-select">
                    <option value="1_day" {{ ($settings['clear_logs_schedule'] ?? '') == '1_day' ? 'selected' : '' }}>1
                        Hari ke belakang</option>
                    <option value="1_week" {{ ($settings['clear_logs_schedule'] ?? '') == '1_week' ? 'selected' : '' }}>1
                        Minggu ke belakang</option>
                    <option value="1_month" {{ ($settings['clear_logs_schedule'] ?? '') == '1_month' ? 'selected' : '' }}>1
                        Bulan ke belakang</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </form>

    </div>
@endsection
