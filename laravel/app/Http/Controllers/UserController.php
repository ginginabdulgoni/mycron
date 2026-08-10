<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->get();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,user',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
        ]);

        return response()->json(['status' => 'success', 'message' => 'User berhasil ditambahkan.', 'data' => $user]);
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user->only(['id', 'name', 'email', 'role']));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin,user',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json(['status' => 'success', 'message' => 'User berhasil diperbarui.']);
    }

    public function destroy($id)
    {
        if ((int) $id === (int) auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        User::destroy($id);

        return response()->json(['status' => 'success', 'message' => 'User berhasil dihapus.']);
    }
}
