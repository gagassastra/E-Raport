@extends('layouts.app')
@section('title', 'Role & Hak Akses')
@section('content')
<div class="page-head"><div><h1>Kelola Lima Role & Hak Akses</h1><p>Sistem menggunakan lima role tetap (Super Admin, Kepala PKBM, Operator Sekolah, Guru/Wali Kelas, Peserta Didik) tanpa role Guru Mata Pelajaran terpisah.</p></div></div>

<div class="grid g2">
@foreach($roles as $role)
    <div class="card">
        <div class="card-h">
            <div><h3>{{ $role->nama }}</h3><div class="muted small">{{ $role->deskripsi }}</div></div>
            <span class="badge blue">{{ $role->users_count }} akun</span>
        </div>
        <div class="card-b">
            @if($role->slug === 'super_admin')
                <div class="alert info">@icon('shield')<div>Super Admin memiliki seluruh hak akses sistem secara penuh.</div></div>
            @else
                <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                    @csrf @method('PUT')
                    @php($owned = $role->permissions->pluck('id')->flip())
                    @foreach($permissionsByGroup as $group => $perms)
                        <div class="mb">
                            <div class="small muted" style="font-weight:700;text-transform:uppercase;margin-bottom:4px">{{ $group }}</div>
                            <div class="grid g2" style="gap:6px">
                                @foreach($perms as $p)
                                    <label class="check small">
                                        <input type="checkbox" name="permissions[]" value="{{ $p->id }}" @checked($owned->has($p->id))>
                                        <span>{{ $p->nama }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <button class="btn sm" type="submit">@icon('check') Simpan Hak Akses {{ $role->nama }}</button>
                </form>
            @endif
        </div>
    </div>
@endforeach
</div>
@endsection
