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

            <hr class="my-4">

            <!-- Auto-delete RTO Cronjobs -->
            <h5 class="mb-3">Penghapusan Otomatis Cronjob Domain RTO</h5>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="auto_delete_rto_active" id="auto_delete_rto_active" class="form-check-input"
                        {{ isset($settings['auto_delete_rto_active']) && $settings['auto_delete_rto_active'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="auto_delete_rto_active">
                        Aktifkan Penghapusan Otomatis Cronjob untuk Domain RTO
                    </label>
                </div>
                <small class="text-muted">Cronjob akan dihapus otomatis jika domain RTO melebihi batas hari yang ditentukan</small>
            </div>

            <div class="mb-3">
                <label for="auto_delete_rto_days" class="form-label">Batas Hari Domain RTO</label>
                <input type="number" name="auto_delete_rto_days" id="auto_delete_rto_days" class="form-control"
                    min="1" max="365" value="{{ $settings['auto_delete_rto_days'] ?? 3 }}">
                <small class="text-muted">Default: 3 hari. Cronjob akan dihapus jika domain RTO sudah melebihi jumlah hari ini.</small>
            </div>

            <div class="mb-3">
                <label for="rto_excluded_domains" class="form-label">Domain Dikecualikan dari Pengecekan RTO</label>
                <textarea name="rto_excluded_domains" id="rto_excluded_domains" class="form-control" rows="3">{{ $settings['rto_excluded_domains'] ?? '' }}</textarea>
                <small class="text-muted">
                    Masukkan domain yang ingin dikecualikan dari pengecekan RTO, satu per baris.
                    Contoh: localhost, 127.0.0.1, example.local
                </small>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </form>

    </div>
@endsection
