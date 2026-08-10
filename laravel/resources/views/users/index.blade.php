<head>

    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

@extends('layouts.app')
@section('title', 'Manajemen User')
@section('content')
    <h2 class="mb-4">Manajemen User</h2>
    <button class="btn btn-primary mb-3" onclick="openCreateModal()">+ Tambah User</button>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="userTable">
            @foreach ($users as $user)
                <tr id="row-{{ $user->id }}">
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role }}</td>
                    <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editUser({{ $user->id }})">Edit</button>
                        @if ($user->id !== auth()->id())
                            <button class="btn btn-sm btn-danger" onclick="deleteUser({{ $user->id }})">Hapus</button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Modal Form User -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="userForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Form User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="user_id">
                        <div class="mb-2">
                            <label>Nama</label>
                            <input type="text" class="form-control" name="name" id="name" required>
                        </div>
                        <div class="mb-2">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="mb-2">
                            <label>Password <small class="text-muted" id="password-hint"></small></label>
                            <input type="password" class="form-control" name="password" id="password">
                        </div>
                        <div class="mb-2">
                            <label>Role</label>
                            <select class="form-control" name="role" id="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
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
