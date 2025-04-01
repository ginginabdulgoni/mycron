<h2>Log Cronjob: {{ $cronjob->name }}</h2>
<a href="{{ route('cronjobs.index') }}">← Kembali</a>
<table class="table table-bordered mt-3">
    <thead>
        <tr>
            <th>Waktu</th>
            <th>Status</th>
            <th>Response</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($cronjob->logs->sortByDesc('run_at') as $log)
            <tr>
                <td>{{ $log->run_at }}</td>
                <td>{{ $log->status }}</td>
                <td>{{ $log->response }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">Belum ada log</td>
            </tr>
        @endforelse
    </tbody>
</table>
