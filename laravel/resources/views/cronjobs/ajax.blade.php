@extends('layouts.app')
@section('title', 'Manajemen Cronjob')
@section('content')
    <style>
        .rto-row > td {
            background-color: #fff1f2 !important;
        }
        .rto-row:hover > td {
            background-color: #ffe4e6 !important;
        }
        .rto-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2em 0.55em;
            font-size: 0.7rem;
            font-weight: 700;
            color: #be123c;
            background-color: #fecdd3;
            border-radius: 999px;
            margin-left: 0.45rem;
        }
        .url-cell {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Manajemen Cronjob</h1>
            <p class="page-subtitle">Kelola daftar URL, interval eksekusi, dan log penjadwalan otomatis</p>
        </div>
        <div class="d-flex gap-2">
            @can('admin')
                <button class="btn btn-outline-danger d-inline-flex align-items-center gap-1" onclick="deleteSelected()">
                    <i class="bi bi-trash3"></i>
                    <span>Hapus Terpilih</span>
                </button>
            @else
                <button class="btn btn-outline-danger d-inline-flex align-items-center gap-1" onclick="deleteSelected()">
                    <i class="bi bi-trash3"></i>
                    <span>Hapus Terpilih</span>
                </button>
            @endcan
            <button class="btn btn-primary d-inline-flex align-items-center gap-1" onclick="openCreateModal()">
                <i class="bi bi-plus-lg"></i>
                <span>Tambah Cronjob</span>
            </button>
        </div>
    </div>

    <div class="card-modern p-3 p-md-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="cronTable">
                <thead>
                    <tr>
                        <th style="width: 36px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <th>Nama Tugas</th>
                        <th>Target URL</th>
                        <th>Schedule</th>
                        <th>Terakhir Eksekusi</th>
                        <th>Status</th>
                        <th>Simpan Log</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="cronjobTable">
                    <!-- Data akan diload via AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Cronjob -->
    <div class="modal fade" id="cronModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="cronForm" class="w-100">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2">
                            <i class="bi bi-alarm text-primary"></i>
                            <span>Form Cronjob</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="cron_id">

                        <div class="mb-3">
                            <label class="form-label" for="name">Nama Cronjob</label>
                            <input type="text" class="form-control" name="name" id="name" placeholder="Contoh: Sync Database Harian" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="url">Target URL</label>
                            <input type="url" class="form-control font-mono" name="url" id="url" placeholder="https://domain.com/api/cron" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="interval_value">Interval Eksekusi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">Setiap</span>
                                <input type="number" min="1" id="interval_value" class="form-control" value="1">
                                <select id="interval_type" class="form-select">
                                    <option value="minute">Menit</option>
                                    <option value="hour">Jam</option>
                                    <option value="day">Hari</option>
                                    <option value="week">Minggu</option>
                                    <option value="month">Bulan</option>
                                </select>
                            </div>
                            <input type="hidden" name="schedule" id="schedule" value="* * * * *">
                        </div>

                        <div class="p-3 rounded-3 bg-light border d-flex flex-column gap-2 mt-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="active" id="active" checked>
                                <label class="form-check-label fw-medium" for="active">Aktifkan Cronjob</label>
                            </div>

                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="save_logs" id="save_logs">
                                <label class="form-check-label fw-medium" for="save_logs">Simpan Riwayat Log Eksekusi</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Simpan Cronjob
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        const modal = new bootstrap.Modal(document.getElementById('cronModal'))

        function openCreateModal() {
            $('#cronForm')[0].reset();
            $('#cron_id').val('');
            generateCron();
            modal.show();
        }

        function editCronjob(id) {
            $.get(`/cronjobs/edit/${id}`, function(data) {
                $('#cron_id').val(data.id);
                $('#name').val(data.name);
                $('#url').val(data.url);
                $('#schedule').val(data.schedule);
                $('#active').prop('checked', data.active);
                $('#save_logs').prop('checked', data.save_logs);
                detectIntervalFromCron(data.schedule);
                modal.show();
            });
        }

        $('#interval_value, #interval_type').on('input change', generateCron);

        function generateCron() {
            const value = parseInt($('#interval_value').val());
            const type = $('#interval_type').val();
            let cron = '* * * * *';

            switch (type) {
                case 'minute':
                    cron = `*/${value} * * * *`;
                    break;
                case 'hour':
                    cron = `0 */${value} * * *`;
                    break;
                case 'day':
                    cron = `0 0 */${value} * *`;
                    break;
                case 'week':
                    cron = `0 0 * * ${value % 7}`;
                    break;
                case 'month':
                    cron = `0 0 ${value} * *`;
                    break;
            }
            $('#schedule').val(cron);
        }

        function detectIntervalFromCron(cron) {
            const parts = cron.split(' ');
            if (cron.startsWith('*/')) {
                $('#interval_value').val(parts[0].replace('*/', ''));
                $('#interval_type').val('minute');
            } else if (parts[1].startsWith('*/')) {
                $('#interval_value').val(parts[1].replace('*/', ''));
                $('#interval_type').val('hour');
            } else if (parts[2].startsWith('*/')) {
                $('#interval_value').val(parts[2].replace('*/', ''));
                $('#interval_type').val('day');
            }
        }

        $('#cronForm').submit(function(e) {
            e.preventDefault();
            generateCron();
            const id = $('#cron_id').val();
            const url = id ? `/cronjobs/update/${id}` : `/cronjobs/store`;
            const formData = $(this).serialize();

            $.post(url, formData, function(res) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    modal.hide();
                    table.ajax.reload(null, false);
                });
            }).fail(function(xhr) {
                let message = 'Gagal menyimpan data.';
                if (xhr.responseJSON?.message) message = xhr.responseJSON.message;
                Swal.fire({
                    icon: 'error',
                    title: 'Oops!',
                    text: message
                });
            });
        });

        function deleteCronjob(id) {
            Swal.fire({
                title: 'Yakin mau hapus?',
                text: "Data ini tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/cronjobs/delete/${id}`,
                        method: 'DELETE',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: res.message || 'Data berhasil dihapus.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: 'Gagal menghapus data.'
                            });
                        }
                    });
                }
            });
        }

        function deleteSelected() {
            const selected = $('.select-cron:checked').map(function() {
                return this.value
            }).get();
            if (selected.length === 0) {
                Swal.fire('Oops', 'Pilih data terlebih dahulu.', 'info');
                return;
            }

            Swal.fire({
                title: 'Yakin mau hapus semua yang dipilih?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/cronjobs/bulk-delete`,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            ids: selected
                        },
                        success: function(res) {
                            Swal.fire('Berhasil', res.message || 'Data berhasil dihapus.', 'success');
                            table.ajax.reload(null, false);
                        },
                        error: function() {
                            Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus.', 'error');
                        }
                    });
                }
            });
        }

        let table;

        $(document).ready(function() {
            table = $('#cronTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('cronjobs.data') }}',
                    type: 'GET'
                },
                columns: [
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(data) {
                            return `<input type="checkbox" class="form-check-input select-cron" value="${data}">`;
                        }
                    },
                    {
                        data: 'name',
                        render: function(data, type, row) {
                            const safeName = $('<div>').text(data).html();
                            if (row.is_rto) {
                                return `<span class="fw-semibold text-dark">${safeName}</span><span class="rto-badge"><i class="bi bi-exclamation-circle-fill"></i> RTO</span>`;
                            }
                            return `<span class="fw-semibold text-dark">${safeName}</span>`;
                        }
                    },
                    {
                        data: 'url',
                        render: function(data) {
                            const safeUrl = $('<div>').text(data).html();
                            return `<a href="${safeUrl}" target="_blank" rel="noopener" class="url-cell font-mono small text-decoration-none text-primary" title="${safeUrl}">${safeUrl}</a>`;
                        }
                    },
                    {
                        data: 'schedule',
                        render: function(data) {
                            return `<code class="px-2 py-1 rounded bg-light border text-dark small">${$('<div>').text(data).html()}</code>`;
                        }
                    },
                    {
                        data: 'last_run_at',
                        render: function(data) {
                            return `<span class="text-muted small"><i class="bi bi-clock me-1"></i>${$('<div>').text(data).html()}</span>`;
                        }
                    },
                    {
                        data: 'active',
                        render: function(data) {
                            return data === 'Aktif'
                                ? '<span class="badge-soft-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>'
                                : '<span class="badge-soft-secondary"><i class="bi bi-pause-circle"></i> Nonaktif</span>';
                        }
                    },
                    {
                        data: 'save_logs',
                        render: function(data) {
                            return data === 'Aktif'
                                ? '<span class="badge-soft-info"><i class="bi bi-journal-check"></i> Disimpan</span>'
                                : '<span class="badge-soft-secondary">Tidak</span>';
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        className: 'text-end text-nowrap',
                        render: function(data) {
                            return `
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-sm btn-light border text-info" href="/cronlogs/${data}" title="Lihat Log">
                                        <i class="bi bi-terminal"></i> Log
                                    </a>
                                    <button class="btn btn-sm btn-light border text-primary" onclick="editCronjob(${data})" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light border text-danger" onclick="deleteCronjob(${data})" title="Hapus">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ],
                rowCallback: function(row, data) {
                    if (data.is_rto) {
                        $(row).addClass('rto-row');
                    }
                },
                language: {
                    emptyTable: "Belum ada data cronjob tersedia",
                    processing: "Memuat data...",
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ baris",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ cronjob",
                    infoEmpty: "Menampilkan 0 cronjob",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Berikutnya",
                        previous: "Sebelumnya"
                    }
                },
                order: [[1, 'asc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
            });

            $('#selectAll').on('change', function() {
                $('.select-cron').prop('checked', this.checked);
            });
        });
    </script>
@endsection
