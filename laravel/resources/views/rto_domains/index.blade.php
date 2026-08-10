<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

@extends('layouts.app')
@section('title', 'Domain RTO')
@section('content')
    <h2 class="mb-4">Domain RTO (Request Timeout)</h2>

    <div class="alert alert-info">
        <strong>Info:</strong> Daftar domain yang mengalami timeout saat di-ping. Domain ini mungkin tidak aktif atau bermasalah.
        Sistem melakukan pengecekan setiap 10 menit.
    </div>

    @if($rtoDomains->count() > 0)
        @can('admin')
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-danger" onclick="deleteSelectedDomains()">🗑️ Hapus Cronjob Terpilih</button>
            </div>
        @endcan

        <table class="table table-bordered table-striped" id="rtoTable">
            <thead>
                <tr>
                    @can('admin')
                        <th style="width: 40px;"><input type="checkbox" id="selectAllDomains"></th>
                    @endcan
                    <th>Domain</th>
                    <th>Jumlah Cronjob</th>
                    <th>RTO Count</th>
                    <th>Pertama Terdeteksi</th>
                    <th>Terakhir Dicek</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rtoDomains as $rto)
                    <tr id="row-{{ $rto->id }}">
                        @can('admin')
                            <td><input type="checkbox" class="select-domain" value="{{ $rto->id }}" data-domain="{{ $rto->domain }}"></td>
                        @endcan
                        <td><strong>{{ $rto->domain }}</strong></td>
                        <td>
                            <span class="badge bg-info">{{ $rto->cronjob_count }}</span>
                        </td>
                        <td>
                            <span class="badge bg-warning">{{ $rto->rto_count }}</span>
                        </td>
                        <td>
                            {{ $rto->first_detected_at ? \Carbon\Carbon::parse($rto->first_detected_at)->timezone($timezone)->format('d-m-Y H:i') : '-' }}
                        </td>
                        <td>
                            {{ $rto->last_checked_at ? \Carbon\Carbon::parse($rto->last_checked_at)->timezone($timezone)->format('d-m-Y H:i') : '-' }}
                        </td>
                        <td>
                            @can('admin')
                                <button class="btn btn-sm btn-danger" onclick="deleteCronjobs({{ $rto->id }}, '{{ $rto->domain }}')">
                                    Hapus Semua Cronjob
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="removeFromList({{ $rto->id }}, '{{ $rto->domain }}')">
                                    Hapus dari Daftar
                                </button>
                            @else
                                <span class="text-muted">Hanya Admin</span>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="alert alert-success">
            <strong>Bagus!</strong> Tidak ada domain yang mengalami RTO saat ini.
        </div>
    @endif

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        function deleteCronjobs(id, domain) {
            Swal.fire({
                title: 'Yakin mau hapus semua cronjob?',
                text: `Semua cronjob dengan domain ${domain} akan dihapus!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/rto-domains/${id}/delete-cronjobs`,
                        method: 'DELETE',
                        success: function(res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            $(`#row-${id}`).remove();

                            if ($('#rtoTable tbody tr').length === 0) {
                                location.reload();
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: 'Gagal menghapus cronjob.'
                            });
                        }
                    });
                }
            });
        }

        function removeFromList(id, domain) {
            Swal.fire({
                title: 'Hapus dari daftar RTO?',
                text: `Domain ${domain} akan dihapus dari daftar RTO (cronjob tidak akan dihapus)`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/rto-domains/${id}`,
                        method: 'DELETE',
                        success: function(res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            $(`#row-${id}`).remove();

                            if ($('#rtoTable tbody tr').length === 0) {
                                location.reload();
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: 'Gagal menghapus dari daftar.'
                            });
                        }
                    });
                }
            });
        }

        function deleteSelectedDomains() {
            const selected = $('.select-domain:checked').map(function() {
                return {
                    id: this.value,
                    domain: $(this).data('domain')
                };
            }).get();

            if (selected.length === 0) {
                Swal.fire('Oops', 'Pilih domain terlebih dahulu.', 'info');
                return;
            }

            const domainList = selected.map(s => s.domain).join(', ');

            Swal.fire({
                title: 'Yakin mau hapus semua cronjob dari domain yang dipilih?',
                html: `<strong>Domain yang dipilih:</strong><br>${domainList}<br><br>Semua cronjob dari domain ini akan dihapus!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus semua!',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#d33',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    const ids = selected.map(s => s.id);
                    $.ajax({
                        url: `/rto-domains/bulk-delete-cronjobs`,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            ids: ids
                        },
                        success: function(res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus.', 'error');
                        }
                    });
                }
            });
        }

        $(document).ready(function() {
            @can('admin')
                const orderColumn = 4; // Dengan checkbox di kolom 0
            @else
                const orderColumn = 3; // Tanpa checkbox
            @endcan

            $('#rtoTable').DataTable({
                language: {
                    emptyTable: "Belum ada domain RTO"
                },
                order: [[orderColumn, 'desc']],
                columnDefs: [
                    @can('admin')
                    { orderable: false, targets: 0 } // Disable sorting pada checkbox column
                    @endcan
                ]
            });

            $('#selectAllDomains').on('change', function() {
                $('.select-domain').prop('checked', this.checked);
            });
        });
    </script>
@endsection
