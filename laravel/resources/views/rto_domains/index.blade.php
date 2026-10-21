@extends('layouts.app')
@section('title', 'Domain RTO')
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Domain RTO (Request Timeout)</h1>
            <p class="page-subtitle">Daftar domain yang mengalami timeout saat di-ping (dicek otomatis setiap 10 menit)</p>
        </div>
        @if($rtoDomains->count() > 0)
            @can('admin')
                <button class="btn btn-danger d-inline-flex align-items-center gap-1" onclick="deleteSelectedDomains()">
                    <i class="bi bi-trash3"></i>
                    <span>Hapus Cronjob Terpilih</span>
                </button>
            @endcan
        @endif
    </div>

    <div class="card-modern p-3 mb-4 border-start border-4 border-primary bg-white">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-info-circle-fill fs-5"></i>
            </div>
            <div class="small text-secondary">
                <strong class="text-dark">Informasi Pemantauan:</strong> Domain yang terdaftar di bawah ini mengalami kegagalan koneksi / timeout saat dieksekusi oleh worker. Anda dapat menghapus semua cronjob terkait domain tersebut atau menghapusnya dari daftar pantauan.
            </div>
        </div>
    </div>

    @if($rtoDomains->count() > 0)
        <div class="card-modern p-3 p-md-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="rtoTable">
                    <thead>
                        <tr>
                            @can('admin')
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAllDomains"></th>
                            @endcan
                            <th>Nama Domain</th>
                            <th>Jumlah Cronjob</th>
                            <th>Total RTO</th>
                            <th>Pertama Terdeteksi</th>
                            <th>Terakhir Dicek</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rtoDomains as $rto)
                            <tr id="row-{{ $rto->id }}">
                                @can('admin')
                                    <td>
                                        <input type="checkbox" class="form-check-input select-domain" value="{{ $rto->id }}" data-domain="{{ $rto->domain }}">
                                    </td>
                                @endcan
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-globe text-danger"></i>
                                        <span class="fw-bold text-dark font-mono">{{ $rto->domain }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-soft-info">{{ $rto->cronjob_count }} Cronjob</span>
                                </td>
                                <td>
                                    <span class="badge-soft-warning"><i class="bi bi-lightning-fill"></i> {{ $rto->rto_count }}x</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $rto->first_detected_at ? \Carbon\Carbon::parse($rto->first_detected_at)->timezone($timezone)->format('d-m-Y H:i') : '-' }}
                                </td>
                                <td class="small text-muted">
                                    {{ $rto->last_checked_at ? \Carbon\Carbon::parse($rto->last_checked_at)->timezone($timezone)->format('d-m-Y H:i') : '-' }}
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('admin')
                                        <div class="d-inline-flex gap-1">
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCronjobs({{ $rto->id }}, '{{ $rto->domain }}')">
                                                <i class="bi bi-trash3 me-1"></i>Hapus Cronjob
                                            </button>
                                            <button class="btn btn-sm btn-light border text-secondary" onclick="removeFromList({{ $rto->id }}, '{{ $rto->domain }}')">
                                                <i class="bi bi-x-lg me-1"></i>Abaikan
                                            </button>
                                        </div>
                                    @else
                                        <span class="badge-soft-secondary">Hanya Admin</span>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card-modern p-5 text-center">
            <div class="mx-auto mb-3 rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                <i class="bi bi-shield-check fs-2"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Semua Domain Berjalan Normal</h5>
            <p class="text-muted small mb-0">Tidak ada domain yang mengalami Request Timeout (RTO) saat ini.</p>
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
                const orderColumn = 4;
            @else
                const orderColumn = 3;
            @endcan

            if ($('#rtoTable').length) {
                $('#rtoTable').DataTable({
                    language: {
                        emptyTable: "Belum ada domain RTO",
                        search: "Cari domain:",
                        lengthMenu: "Tampilkan _MENU_ domain"
                    },
                    order: [[orderColumn, 'desc']],
                    columnDefs: [
                        @can('admin')
                        { orderable: false, targets: 0 }
                        @endcan
                    ]
                });
            }

            $('#selectAllDomains').on('change', function() {
                $('.select-domain').prop('checked', this.checked);
            });
        });
    </script>
@endsection
