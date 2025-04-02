@extends('layouts.app')
@section('title', 'Log Cronjob')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Log Cronjob: {{ $cronjob->name }}</h4>
        <form action="{{ route('cronlogs.clear', $cronjob->id) }}" method="POST"
            onsubmit="return confirm('Yakin ingin menghapus semua log ini?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger btn-sm">🗑️ Clear Logs</button>
        </form>
    </div>
    <a href="{{ route('cronjobs.index') }}" class="btn btn-secondary btn-sm mb-3">← Kembali ke Cronjob</a>

    <table class="table table-bordered table-striped" id="logTable">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Status</th>
                <th>Response</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cronjob->logs->sortByDesc('run_at') as $log)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($log->run_at)->format('d-m-Y H:i:s') }}</td>
                    <td>{{ ucfirst($log->status) }}</td>
                    <td>{{ $log->response }}</td>
                </tr>
            @endforeach
        </tbody>

    </table>

    {{-- CDN DataTables --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#logTable').DataTable({
                order: [
                    [0, 'desc']
                ],
                language: {
                    emptyTable: "Belum ada log tersedia"
                }
            });

        });
    </script>
@endsection
