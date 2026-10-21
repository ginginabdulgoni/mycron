@extends('layouts.app')
@section('title', 'Manajemen User')
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Manajemen User</h1>
            <p class="page-subtitle">Kelola akun pengguna dan hak akses administrator pada sistem MyCron</p>
        </div>
        <button class="btn btn-primary d-inline-flex align-items-center gap-1" onclick="openCreateModal()">
            <i class="bi bi-person-plus-fill"></i>
            <span>Tambah User</span>
        </button>
    </div>

    <div class="card-modern overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Terdaftar</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="userTable">
                    @foreach ($users as $user)
                        <tr id="row-{{ $user->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="user-avatar" style="width: 34px; height: 34px;">
                                        {{ substr($user->name, 0, 1) }}
                                    </span>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $user->name }}</div>
                                        @if ($user->id === auth()->id())
                                            <span class="text-muted" style="font-size: 0.72rem;">Akun Anda</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td>
                                @if ($user->role === 'admin')
                                    <span class="badge-soft-primary text-uppercase"><i class="bi bi-shield-lock-fill"></i> Admin</span>
                                @else
                                    <span class="badge-soft-secondary text-uppercase"><i class="bi bi-person"></i> User</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $user->created_at->format('d-m-Y H:i') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-light border text-primary" onclick="editUser({{ $user->id }})">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    @if ($user->id !== auth()->id())
                                        <button class="btn btn-sm btn-light border text-danger" onclick="deleteUser({{ $user->id }})" title="Hapus">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form User -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="userForm" class="w-100">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2">
                            <i class="bi bi-person-gear text-primary"></i>
                            <span>Form Pengguna</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="user_id">
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama Lengkap</label>
                            <input type="text" class="form-control" name="name" id="name" placeholder="Nama lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Alamat Email</label>
                            <input type="email" class="form-control" name="email" id="email" placeholder="nama@email.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">
                                Password <small class="text-muted fw-normal" id="password-hint"></small>
                            </label>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Minimal 6 karakter">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="role">Hak Akses (Role)</label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="user">User Standar</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i>Simpan User
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

        const modal = new bootstrap.Modal(document.getElementById('userModal'))

        function openCreateModal() {
            $('#userForm')[0].reset();
            $('#user_id').val('');
            $('#password').prop('required', true);
            $('#password-hint').text('');
            modal.show();
        }

        function editUser(id) {
            $.get(`/users/edit/${id}`, function(data) {
                $('#user_id').val(data.id);
                $('#name').val(data.name);
                $('#email').val(data.email);
                $('#role').val(data.role);
                $('#password').val('').prop('required', false);
                $('#password-hint').text('(kosongkan jika tidak ingin mengubah)');
                modal.show();
            });
        }

        $('#userForm').submit(function(e) {
            e.preventDefault();
            const id = $('#user_id').val();
            const url = id ? `/users/update/${id}` : `/users/store`;
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
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Oops!',
                    text: message
                });
            });
        });

        function deleteUser(id) {
            Swal.fire({
                title: 'Yakin mau hapus user ini?',
                text: "Data ini tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/users/delete/${id}`,
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
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: xhr.responseJSON?.message || 'Gagal menghapus data.'
                            });
                        }
                    });
                }
            });
        }
    </script>
@endsection
