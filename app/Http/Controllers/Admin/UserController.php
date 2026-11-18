<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::orderBy('id')->get();
        $editing = $request->filled('edit') ? User::find($request->query('edit')) : null;
        $users = User::with('role')
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->query('role_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('username', 'like', '%'.$request->query('q').'%')->orWhere('email', 'like', '%'.$request->query('q').'%')))
            ->orderBy('role_id')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.users', compact('users', 'roles', 'editing'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => ($data['email'] ?? null) ?: null,
            'phone' => ($data['phone'] ?? null) ?: null,
            'role_id' => $data['role_id'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        ActivityLogger::log('tambah', 'pengguna', "Menambahkan akun pengguna {$user->name} ({$user->username}).", $user);

        return redirect()->route('admin.users.index')->with('success', "Pengguna {$user->name} berhasil ditambahkan.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username,'.$user->id],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && ! $request->boolean('is_active', true)) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $upd = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => ($data['email'] ?? null) ?: null,
            'phone' => ($data['phone'] ?? null) ?: null,
            'role_id' => $data['role_id'],
            'is_active' => $request->boolean('is_active'),
        ];
        if (filled($data['password'] ?? null)) {
            $upd['password'] = Hash::make($data['password']);
            $upd['must_change_password'] = false;
        }
        $user->update($upd);
        ActivityLogger::log('ubah', 'pengguna', "Memperbarui akun pengguna {$user->name}.", $user);

        return redirect()->route('admin.users.index')->with('success', "Pengguna {$user->name} diperbarui.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }
        if ($user->teacher()->exists() || $user->student()->exists()) {
            return back()->with('error', 'Akun ini terhubung dengan data Guru atau Peserta Didik. Kelola melalui menu Data Guru / Peserta Didik.');
        }
        $nama = $user->name;
        $user->delete();
        ActivityLogger::log('hapus', 'pengguna', "Menghapus pengguna {$nama}.");

        return back()->with('success', "Pengguna {$nama} dihapus.");
    }
}
