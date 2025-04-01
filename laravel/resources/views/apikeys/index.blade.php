<!DOCTYPE html>
<html>

<head>
    <title>Manajemen API Key</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <h2 class="mb-4">Manajemen API Key</h2>
    <button class="btn btn-primary mb-3" onclick="openCreateModal()">+ Tambah API Key</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nama Aplikasi</th>
                <th>API Key</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="apiKeyTable">
            @foreach ($apiKeys as $key)
                <tr id="row-{{ $key->id }}">
                    <td>{{ $key->name }}</td>
                    <td><code>{{ $key->key }}</code></td>
                    <td>{{ $key->active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td>{{ $key->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editApiKey({{ $key->id }})">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteApiKey({{ $key->id }})">Hapus</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Modal Form API Key -->
    <div class="modal fade" id="apiKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="apiKeyForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Form API Key</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="api_id">
                        <div class="mb-2">
                            <label>Nama Aplikasi</label>
                            <input type="text" class="form-control" name="name" id="name" required>
                        </div>
                        <div class="mb-2">
                            <label>API Key</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="key" id="key" readonly>
                                <button type="button" class="btn btn-secondary"
                                    onclick="generateApiKey()">Generate</button>
                            </div>
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

        const modal = new bootstrap.Modal(document.getElementById('apiKeyModal'))

        function openCreateModal() {
            $('#apiKeyForm')[0].reset();
            $('#api_id').val('');
            generateApiKey();
            modal.show();
        }

        function editApiKey(id) {
            $.get(`/apikeys/edit/${id}`, function(data) {
                $('#api_id').val(data.id);
                $('#name').val(data.name);
                $('#key').val(data.key);
                $('#active').prop('checked', data.active);
                modal.show();
            });
        }

        function generateApiKey() {
            const uuid = crypto.randomUUID();
            $('#key').val(uuid);
        }

        $('#apiKeyForm').submit(function(e) {
            e.preventDefault();
            const id = $('#api_id').val();
            const url = id ? `/apikeys/update/${id}` : `/apikeys/store`;
            const formData = $(this).serialize();

            $.post(url, formData, function(res) {
                alert(res.message);
                location.reload();
            }).fail(function() {
                alert('Gagal menyimpan data.');
            });
        });

        function deleteApiKey(id) {
            if (confirm('Yakin mau hapus?')) {
                $.ajax({
                    url: `/apikeys/delete/${id}`,
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
