@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Pengaturan Aplikasi</h1>
            <p class="page-subtitle">Konfigurasi identitas aplikasi, zona waktu, retensi log, dan penanganan domain RTO</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.store') }}">
        @csrf
        <div class="row g-4">
            {{-- Pengaturan Umum & Retensi Log --}}
            <div class="col-lg-6">
                <div class="card-modern p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-sliders text-primary fs-5"></i>
                        <h5 class="fw-bold mb-0">Identitas & Zona Waktu</h5>
                    </div>

                    <div class="mb-3">
                        <label for="company_name" class="form-label">Nama Perusahaan / Aplikasi</label>
                        <input type="text" name="company_name" id="company_name" class="form-control"
                            placeholder="MyCron" value="{{ $settings['company_name'] ?? '' }}">
                    </div>

                    <div class="mb-4">
                        <label for="timezone" class="form-label">Zona Waktu Sistem</label>
                        <select name="timezone" id="timezone" class="form-select">
                            @foreach (timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" {{ ($settings['timezone'] ?? '') == $tz ? 'selected' : '' }}>
                                    {{ $tz }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom mt-4">
                        <i class="bi bi-journal-x text-primary fs-5"></i>
                        <h5 class="fw-bold mb-0">Pembersihan Log Otomatis</h5>
                    </div>

                    <div class="p-3 rounded-3 bg-light border mb-3">
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox" name="clear_logs_active" id="clear_logs_active" class="form-check-input"
                                {{ isset($settings['clear_logs_active']) && $settings['clear_logs_active'] ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="clear_logs_active">Aktifkan Jadwal Pembersihan Log</label>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label for="clear_logs_schedule" class="form-label">Retensi Riwayat Log</label>
                        <select name="clear_logs_schedule" id="clear_logs_schedule" class="form-select">
                            <option value="1_day" {{ ($settings['clear_logs_schedule'] ?? '') == '1_day' ? 'selected' : '' }}>
                                1 Hari ke belakang
                            </option>
                            <option value="1_week" {{ ($settings['clear_logs_schedule'] ?? '') == '1_week' ? 'selected' : '' }}>
                                1 Minggu ke belakang
                            </option>
                            <option value="1_month" {{ ($settings['clear_logs_schedule'] ?? '') == '1_month' ? 'selected' : '' }}>
                                1 Bulan ke belakang
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Pengaturan RTO --}}
            <div class="col-lg-6">
                <div class="card-modern p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-shield-exclamation text-warning fs-5"></i>
                        <h5 class="fw-bold mb-0">Penanganan Otomatis Domain RTO</h5>
                    </div>

                    <div class="p-3 rounded-3 bg-light border mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="auto_delete_rto_active" id="auto_delete_rto_active" class="form-check-input"
                                {{ isset($settings['auto_delete_rto_active']) && $settings['auto_delete_rto_active'] ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="auto_delete_rto_active">
                                Hapus Otomatis Cronjob Domain RTO
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Cronjob akan dihapus otomatis apabila domain RTO melampaui batas hari yang ditentukan.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="auto_delete_rto_days" class="form-label">Batas Hari Domain RTO</label>
                        <div class="input-group">
                            <input type="number" name="auto_delete_rto_days" id="auto_delete_rto_days" class="form-control"
                                min="1" max="365" value="{{ $settings['auto_delete_rto_days'] ?? 3 }}">
                            <span class="input-group-text bg-light text-muted">Hari</span>
                        </div>
                        <small class="text-muted">Default: 3 hari.</small>
                    </div>

                    <div class="mb-0">
                        <label for="rto_excluded_domains" class="form-label">Daftar Whitelist Domain (Dikecualikan dari RTO)</label>
                        <textarea name="rto_excluded_domains" id="rto_excluded_domains" class="form-control font-mono" rows="4"
                            placeholder="localhost&#10;127.0.0.1&#10;example.local">{{ $settings['rto_excluded_domains'] ?? '' }}</textarea>
                        <small class="text-muted">
                            Masukkan satu domain per baris untuk dikecualikan dari pengecekan RTO.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="bi bi-check2-circle me-1"></i>Simpan Pengaturan
            </button>
        </div>
    </form>
@endsection
