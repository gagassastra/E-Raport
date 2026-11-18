@extends('layouts.app')
@section('title', 'Kelola Pengguna')
@section('content')
<div class="page-head"><div><h1>Kelola Seluruh Pengguna</h1><p>Pengelolaan akun untuk 5 role: Super Admin, Kepala PKBM, Operator Sekolah, Guru/Wali Kelas, dan Peserta Didik.</p></div></div>

<div class="grid g-main" style="align-items:start">
    <div class="card">
        <form class="filters" method="GET">
            <div class="field search"><label>Cari</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Nama / Username / Email"></div>
            <div class="field"><label>Role</label><select class="input" name="role_id" data-autosubmit><option value="">Semua Role</option>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->nama }}</option>@endforeach</select></div>
            <button class="btn light" type="submit">@icon('search')</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Terakhir Masuk</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td><b>{{ $u->name }}</b><div class="muted small">{{ $u->email ?: $u->phone ?: '-' }}</div></td>
                    <td><code>{{ $u->username }}</code></td>
                    <td><span class="badge navy">{{ $u->role?->nama ?? '-' }}</span></td>
                    <td class="small muted">{{ $u->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                    <td><span class="badge {{ $u->is_active ? 'green' : 'red' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="actions">
                        <a class="btn light sm" href="{{ route('admin.users.index', array_merge(request()->query(), ['edit' => $u->id])) }}">@icon('edit') Edit</a>
                        @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" style="display:inline" data-confirm="Hapus pengguna {{ $u->name }}?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $users->links() }}
    </div>

    <div class="card">
        <div class="card-h"><h3>{{ $editing ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}</h3></div>
        <div class="card-b">
            <form method="POST" action="{{ $editing ? route('admin.users.update', $editing) : route('admin.users.store') }}">
                @csrf @if($editing) @method('PUT') @endif
                <div class="grid">
                    <x-field name="name" label="Nama Lengkap" :value="$editing?->name" required />
                    <x-field name="username" label="Nama Pengguna (Username)" :value="$editing?->username" required />
                    <x-field name="role_id" type="select" label="Role" required>
                        @foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $editing?->role_id) == $r->id)>{{ $r->nama }}</option>@endforeach
                    </x-field>
                    <x-field name="email" type="email" label="Email" :value="$editing?->email" />
                    <x-field name="phone" label="Nomor Telepon" :value="$editing?->phone" />
                    <x-field name="password" type="password" label="{{ $editing ? 'Kata Sandi Baru (Kosongkan jika tetap)' : 'Kata Sandi' }}" :required="!$editing" />
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing?->is_active ?? true))> Akun Aktif</label>
                </div>
                <div class="btn-group mt">
                    <button class="btn" type="submit">@icon('check') {{ $editing ? 'Simpan Perubahan' : 'Buat Pengguna' }}</button>
                    @if($editing)<a class="btn light" href="{{ route('admin.users.index') }}">Batal</a>@endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
