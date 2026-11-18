<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('id')->get();
        $permissionsByGroup = Permission::orderBy('kelompok')->orderBy('id')->get()->groupBy('kelompok');

        return view('admin.roles', compact('roles', 'permissionsByGroup'));
    }

    public function update(Request $request, Role $role)
    {
        if ($role->slug === 'super_admin') {
            return back()->with('info', 'Super Admin selalu memiliki akses penuh ke seluruh fitur sistem.');
        }
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);
        ActivityLogger::log('ubah_hak_akses', 'role', "Memperbarui matriks hak akses untuk role {$role->nama}.", $role);

        return back()->with('success', "Hak akses untuk role {$role->nama} berhasil diperbarui.");
    }
}
