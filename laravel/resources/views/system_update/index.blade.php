<head>

    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

@extends('layouts.app')
@section('title', 'Update System')
@section('content')
    <h2 class="mb-4">Update System</h2>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-subtitle mb-2 text-muted">Versi saat ini</h6>
            @if ($current)
                <p class="mb-1"><code>{{ substr($current['hash'], 0, 8) }}</code> &mdash; {{ $current['message'] }}</p>
                <p class="text-muted small mb-0">{{ $current['date'] }}</p>
            @else
                <p class="text-muted mb-0">Belum ada riwayat pull dari GitHub di server ini.</p>
            @endif
        </div>
    </div>

    <button class="btn btn-secondary" id="btnCheck" onclick="checkUpdate()">Cek Update</button>
    <button class="btn btn-primary" id="btnPull" onclick="doPull()" disabled>Update Sekarang</button>

    <div id="checkResult" class="mt-3"></div>

    <div class="mt-4" id="logWrapper" style="display:none;">
        <h6>Log Proses</h6>
        <pre id="logBox" class="bg-dark text-light p-3 rounded" style="max-height:400px; overflow-y:auto; white-space:pre-wrap;"></pre>
    </div>

    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        function checkUpdate() {
            $('#btnCheck').prop('disabled', true).text('Mengecek...');
            $('#checkResult').html('');

            $.get('{{ route('system-update.check') }}')
                .done(function(res) {
                    if (res.up_to_date) {
                        $('#checkResult').html(
                            '<div class="alert alert-success mb-0">Sudah versi terbaru.</div>');
                        $('#btnPull').prop('disabled', true);
                    } else {
                        $('#checkResult').html(
                            `<div class="alert alert-warning mb-0">Update tersedia: <code>${(res.latest_hash || '').substring(0, 8)}</code> &mdash; ${res.latest_message ?? ''}</div>`
                        );
                        $('#btnPull').prop('disabled', false);
                    }
                })
                .fail(function(xhr) {
                    $('#checkResult').html(
                        `<div class="alert alert-danger mb-0">${xhr.responseJSON?.message || 'Gagal cek update.'}</div>`
                    );
                })
                .always(function() {
                    $('#btnCheck').prop('disabled', false).text('Cek Update');
                });
        }

        function appendLog(entry) {
            const box = document.getElementById('logBox');
            const icon = entry.ok ? '✅' : '❌';
            box.textContent += `${icon} ${entry.label}\n${entry.output}\n\n`;
            box.scrollTop = box.scrollHeight;
        }

        function doPull() {
            Swal.fire({
                title: 'Update sistem sekarang?',
                text: 'Ini akan menarik kode terbaru dari GitHub dan menerapkannya ke server produksi.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, update!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;

                $('#btnPull, #btnCheck').prop('disabled', true);
                $('#logWrapper').show();
                $('#logBox').text('');

                $.post('{{ route('system-update.pull') }}')
                    .done(function(res) {
                        (res.log || []).forEach(appendLog);
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.message
                        }).then(() => location.reload());
                    })
                    .fail(function(xhr) {
                        (xhr.responseJSON?.log || []).forEach(appendLog);
                        Swal.fire({
                            icon: 'error',
                            title: 'Update gagal',
                            text: xhr.responseJSON?.message || 'Terjadi kesalahan saat update.'
                        });
                    })
                    .always(function() {
                        $('#btnCheck').prop('disabled', false);
                    });
            });
        }
    </script>
@endsection
