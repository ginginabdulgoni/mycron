<head>

    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

@extends('layouts.app')
@section('title', 'Log Cronjob')
@section('content')
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
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }).fail(function() {
                let message = 'Gagal menyimpan data.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Oops!',
                    text: message
                });
            });
        });

        function deleteApiKey(id) {
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
                        url: `/apikeys/delete/${id}`,
                        method: 'DELETE',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(res) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: res.message || 'Data berhasil dihapus.',
                                timer: 2000,
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
    </script>
@endsection
