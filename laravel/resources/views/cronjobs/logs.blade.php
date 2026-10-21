@extends('layouts.app')
@section('title', 'Log Cronjob')

@section('content')
    <style>
        .log-body-pre {
            background: #0f172a;
            color: #e2e8f0;
            font-size: 0.78rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            white-space: pre-wrap;
            max-height: 150px;
            overflow: auto;
            margin: 0;
        }
    </style>

    <div class="page-header">
        <div>
            <div class="mb-2">
                <a href="{{ route('cronjobs.index') }}" class="text-decoration-none small fw-semibold text-muted d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Kembali ke Manajemen Cronjob
                </a>
            </div>
            <h1 class="page-title">Log Eksekusi: {{ $cronjob->name }}</h1>
            <p class="page-subtitle font-mono small">{{ $cronjob->url }}</p>
        </div>

        <form action="{{ route('cronlogs.clear', $cronjob->id) }}" method="POST"
            onsubmit="return confirm('Yakin ingin menghapus semua log ini?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger d-inline-flex align-items-center gap-1">
                <i class="bi bi-trash3"></i>
                <span>Bersihkan Log</span>
            </button>
        </form>
    </div>

    <div class="card-modern p-3 p-md-4">
        <div class="table-responsive">
            <table id="logTable" class="table table-hover align-middle w-100">
                <thead>
                    <tr>
                        <th style="width: 180px;">Waktu Eksekusi</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 180px;">HTTP Response</th>
                        <th>Response Body</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-nowrap font-mono small">
                                <i class="bi bi-calendar3 text-muted me-1"></i>
                                {{ \Carbon\Carbon::parse($log->run_at, 'UTC')->timezone($timezone ?? config('app.timezone', 'UTC'))->format('d-m-Y H:i:s') }}
                            </td>
                            <td>
                                @if (strtolower($log->status) === 'success')
                                    <span class="badge-soft-success"><i class="bi bi-check-circle-fill"></i> Success</span>
                                @else
                                    <span class="badge-soft-danger"><i class="bi bi-x-circle-fill"></i> {{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                            <td>
                                <code class="px-2 py-1 rounded bg-light border text-dark small">{{ $log->response }}</code>
                            </td>
                            <td>
                                @if ($log->response_body)
                                    <pre class="log-body-pre">{{ $log->response_body }}</pre>
                                @else
                                    <span class="text-muted small fst-italic"> Kosong </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#logTable').DataTable({
                paging: true,
                lengthChange: true,
                lengthMenu: [10, 25, 50, 100],
                ordering: true,
                order: [[0, 'desc']],
                info: true,
                autoWidth: false,
                language: {
                    emptyTable: "Belum ada riwayat log untuk cronjob ini",
                    search: "Cari log:",
                    lengthMenu: "Tampilkan _MENU_ log",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ log"
                }
            });
        });
    </script>
@endsection
