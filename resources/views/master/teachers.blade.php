@extends('layouts.app')
@section('title', 'Data Guru / Wali Kelas')
@section('content')
<div class="page-head">
    <div><h1>Data Guru / Wali Kelas</h1><p>Guru dan Wali Kelas adalah satu akun dan satu role yang bertanggung jawab terhadap seluruh mata pelajaran pada kelas yang ditugaskan.</p></div>
    @perm('teachers.manage')
    <div class="btn-group">
        <form method="POST" action="{{ route('master.teachers.import') }}" data-confirm="Salin daftar tutor/guru dari database website pkbm_alqudwah (tanpa menimpa yang sudah ada)?" data-confirm-class="sky" data-confirm-ok="Ya, salin">@csrf<button class="btn light" type="submit">@icon('download') Salin dari Database Website</button></form>
        <a class="btn" href="{{ route('master.teachers.create') }}">@icon('plus') Tambah Guru / Wali Kelas</a>
    </div>
    @endperm
</div>

<div class="card">
    <form class="filters" method="GET">
        <div class="field search"><label>Cari Guru</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Nama / NIP / Nomor Identitas"></div>
        <div class="field"><label>Status</label><select class="input" name="status" data-autosubmit><option value="">Semua</option><option value="aktif" @selected(request('status') === 'aktif')>Aktif</option><option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option></select></div>
        <button class="btn light" type="submit">@icon('search') Filter</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nama Lengkap</th><th>NIP / No. Identitas</th><th>JK</th><th>Jabatan</th><th>Akun Login</th><th>Kelas Diampu</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($teachers as $t)
            <tr>
                <td><b>{{ $t->nama }}</b><div class="muted small">{{ $t->telepon ?: $t->email ?: '-' }}</div></td>
                <td>{{ $t->nip ?: '-' }}</td>
                <td>{{ $t->jenis_kelamin }}</td>
                <td>{{ $t->jabatan ?: 'Guru / Wali Kelas' }}</td>
                <td><code>{{ $t->user?->username ?? '-' }}</code></td>
                <td>
                    @forelse($t->classes as $c)<span class="badge blue" style="margin:1px">{{ $c->nama }}</span>@empty<span class="muted small">Belum ditugaskan</span>@endforelse
                </td>
                <td><span class="badge {{ $t->is_active ? 'green' : '' }}">{{ $t->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="actions">
                    @perm('teachers.manage')
                        <a class="btn light sm" href="{{ route('master.teachers.edit', $t) }}">@icon('edit') Edit</a>
                        <form method="POST" action="{{ route('master.teachers.destroy', $t) }}" style="display:inline" data-confirm="Hapus data Guru {{ $t->nama }} beserta akun loginnya?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                    @endperm
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty icon="teacher" text="Belum ada data Guru / Wali Kelas." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $teachers->links() }}
</div>
@endsection
