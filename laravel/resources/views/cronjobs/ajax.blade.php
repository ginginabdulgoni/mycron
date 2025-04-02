<head>

    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

@extends('layouts.app')
@section('title', 'Manajemen Cronjob')
@section('content')
    <h2 class="mb-4">Manajemen Cronjob</h2>
    <div class="d-flex justify-content-between mb-2">
        <button class="btn btn-primary" onclick="openCreateModal()">+ Tambah Cronjob</button>
        <button class="btn btn-danger" onclick="deleteSelected()">🗑️ Hapus Terpilih</button>
    </div>

    <table class="table table-bordered table-striped" id="cronTable">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll"></th>
                <th>Nama</th>
                <th>URL</th>
                <th>Schedule</th>
                <th>Terakhir Eksekusi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="cronjobTable">
            @foreach ($cronjobs as $cron)
                <tr id="row-{{ $cron->id }}">
                    <td><input type="checkbox" class="select-cron" value="{{ $cron->id }}"></td>
                    <td>{{ $cron->name }}</td>
                    <td>{{ $cron->url }}</td>
                    <td>{{ $cron->schedule }}</td>
                    <td>
                        {{ $cron->lastLog?->run_at ? \Illuminate\Support\Carbon::parse($cron->lastLog->run_at)->format('d-m-Y H:i:s') : 'Belum ada' }}
                    </td>
                    <td>{{ $cron->active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editCronjob({{ $cron->id }})">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCronjob({{ $cron->id }})">Hapus</button>
                        <a class="btn btn-sm btn-info" href="{{ route('cronlogs.index', $cron->id) }}">Log</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Modal Form Cronjob -->
    <div class="modal fade" id="cronModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="cronForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Form Cronjob</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="cron_id">
                        <div class="mb-2">
                            <label>Nama</label>
                            <input type="text" class="form-control" name="name" id="name" required>
                        </div>
                        <div class="mb-2">
                            <label>URL</label>
                            <input type="url" class="form-control" name="url" id="url" required>
                        </div>
                        <div class="mb-2">
                            <label>Interval</label>
                            <div class="input-group">
                                <input type="number" min="1" id="interval_value" class="form-control"
                                    value="1">
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
                        <div class="mb-2 form-check">
                            <input type="checkbox" class="form-check-input" name="active" id="active" checked>
                            <label class="form-check-label" for="active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Simpan</button>
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
                    location.reload();
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
                            $(`#row-${id}`).remove();
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
                            selected.forEach(id => $(`#row-${id}`).remove());
                            Swal.fire('Berhasil', res.message || 'Data berhasil dihapus.', 'success');
                        },
                        error: function() {
                            Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus.', 'error');
                        }
                    });
                }
            });
        }

        $(document).ready(function() {
            $('#cronTable').DataTable({
                language: {
                    emptyTable: "Belum ada data tersedia"
                },
                order: [
                    [1, 'asc']
                ]
            });

            $('#selectAll').on('change', function() {
                $('.select-cron').prop('checked', this.checked);
            });
        });
    </script>
@endsection
