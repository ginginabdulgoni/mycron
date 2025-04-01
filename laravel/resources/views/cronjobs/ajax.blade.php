<!DOCTYPE html>
<html>

<head>
    <title>Manajemen Cronjob</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <h2 class="mb-4">Manajemen Cronjob</h2>
    <button class="btn btn-primary mb-3" onclick="openCreateModal()">+ Tambah Cronjob</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nama</th>
                <th>URL</th>
                <th>Schedule</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="cronjobTable">
            @foreach ($cronjobs as $cron)
                <tr id="row-{{ $cron->id }}">
                    <td>{{ $cron->name }}</td>
                    <td>{{ $cron->url }}</td>
                    <td>{{ $cron->schedule }}</td>
                    <td>{{ $cron->active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editCronjob({{ $cron->id }})">Edit</button>
                        <button class="btn btn-sm btn-danger"
                            onclick="deleteCronjob({{ $cron->id }})">Hapus</button>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
            // Optional enhancement: parse back to interval_value + interval_type (basic only)
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
                alert(res.message);
                location.reload();
            }).fail(function() {
                alert('Gagal menyimpan data.');
            });
        });

        function deleteCronjob(id) {
            if (confirm('Yakin mau hapus?')) {
                $.ajax({
                    url: `/cronjobs/delete/${id}`,
                    method: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        alert(res.message);
                        $(`#row-${id}`).remove();
                    },
                    error: function() {
                        alert('Gagal menghapus data.');
                    }
                });
            }
        }
    </script>
</body>

</html>
