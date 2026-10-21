@extends('layouts.app')
@section('title', 'Manajemen API Key')
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Manajemen API Key</h1>
            <p class="page-subtitle">Kelola kunci autentikasi untuk integrasi aplikasi eksternal dengan MyCron</p>
        </div>
        <button class="btn btn-primary d-inline-flex align-items-center gap-1" onclick="openCreateModal()">
            <i class="bi bi-plus-lg"></i>
            <span>Tambah API Key</span>
        </button>
    </div>

    <div class="card-modern overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama Aplikasi</th>
                        <th>API Key</th>
                        <th>Status</th>
                        <th>Dibuat Pada</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="apiKeyTable">
                    @forelse ($apiKeys as $key)
                        <tr id="row-{{ $key->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-2 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <i class="bi bi-key-fill"></i>
                                    </div>
                                    <span class="fw-semibold text-dark">{{ $key->name }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-inline-flex align-items-center gap-2 bg-light border rounded-2 px-2 py-1">
                                    <code class="text-dark small">{{ $key->key }}</code>
                                    <button type="button" class="btn btn-sm p-0 border-0 text-secondary" onclick="copyKey('{{ $key->key }}')" title="Salin API Key">
                                        <i class="bi bi-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                @if ($key->active)
                                    <span class="badge-soft-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                                @else
                                    <span class="badge-soft-secondary"><i class="bi bi-pause-circle"></i> Nonaktif</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $key->created_at->format('d-m-Y H:i') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-light border text-primary" onclick="editApiKey({{ $key->id }})" title="Edit">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-light border text-danger" onclick="deleteApiKey({{ $key->id }})" title="Hapus">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-key fs-2 d-block mb-2"></i>
                                Belum ada API Key yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form API Key -->
    <div class="modal fade" id="apiKeyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="apiKeyForm" class="w-100">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2">
                            <i class="bi bi-key text-primary"></i>
                            <span>Form API Key</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="api_id">
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama Aplikasi</label>
                            <input type="text" class="form-control" name="name" id="name" placeholder="Contoh: Billing Server" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="key">API Key</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-mono bg-light" name="key" id="key" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateApiKey()">
                                    <i class="bi bi-arrow-repeat me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                        <div class="p-3 rounded-3 bg-light border">
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" class="form-check-input" name="active" id="active" checked>
                                <label class="form-check-label fw-medium" for="active">Status Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Simpan API Key
                        </button>
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

        function copyKey(key) {
            navigator.clipboard.writeText(key).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'API Key disalin!',
                    showConfirmButton: false,
                    timer: 1500
                });
            });
        }

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
            }).fail(function(xhr) {
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
