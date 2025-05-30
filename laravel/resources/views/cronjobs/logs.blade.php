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

    <table id="logTable" class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Status</th>
                <th>Response</th>
                <th>Response Body</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($log->run_at)->format('d-m-Y H:i:s') }}</td>
                    <td>{{ ucfirst($log->status) }}</td>
                    <td>{{ $log->response }}</td>
                    <td>
                        <pre style="white-space: pre-wrap; max-height: 150px; overflow:auto;">{{ $log->response_body }}</pre>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>

    <!-- Tambahkan DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#logTable').DataTable({
                "paging": true,
                "lengthChange": true, // ✅ Tampilkan dropdown "Show entries"
                "lengthMenu": [10, 25, 50, 100],
                "ordering": true,
                "info": true,
                "autoWidth": false
            });
        });
    </script>

@endsection
