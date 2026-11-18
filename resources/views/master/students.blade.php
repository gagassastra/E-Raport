@extends('layouts.app')
@section('title', 'Data Peserta Didik')
@section('content')
<div class="page-head">
    <div><h1>Data Peserta Didik</h1><p>Kelola data induk peserta didik program Paket B dan Paket C beserta akun portal rapornya.</p></div>
    <div class="btn-group">
        <a class="btn light" href="{{ route('master.students.export') }}">@icon('download') Ekspor CSV</a>
        @perm('students.manage')
            <a class="btn" href="{{ route('master.students.create') }}">@icon('plus') Tambah Peserta Didik</a>
        @endperm
    </div>
</div>

@perm('students.manage')
<div class="card mb">
    <div class="card-b" style="padding:12px 18px">
        <form method="POST" action="{{ route('master.students.import') }}" enctype="multipart/form-data" class="flex items-center between wrap gap">
            @csrf
            <div class="small"><b>Impor Massal CSV:</b> Kolom <code>nis,nisn,nama,jenis_kelamin,tempat_lahir,tanggal_lahir,agama,alamat,nama_wali,telepon,program_kode,status,tahun_masuk</code> (<code>program_kode</code>: <code>paket_b</code> atau <code>paket_c</code>).</div>
            <div class="flex gap items-center">
                <input class="input" type="file" name="csv" accept=".csv,text/csv" required style="max-width:260px;padding:5px 8px">
                <button class="btn light sm" type="submit">@icon('upload') Impor CSV</button>
            </div>
        </form>
    </div>
</div>
@endperm

<div class="card">
    <form class="filters" method="GET">
        <div class="field search"><label>Cari Peserta Didik</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Nama / NIS / NISN"></div>
        <div class="field"><label>Program</label><select class="input" name="program_id" data-autosubmit><option value="">Semua Program</option>@foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->nama }}</option>@endforeach</select></div>
        <div class="field"><label>Status</label><select class="input" name="status" data-autosubmit><option value="">Semua Status</option>@foreach(\App\Models\Student::STATUS as $k => $lbl)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $lbl }}</option>@endforeach</select></div>
        <div class="field" style="max-width:130px"><label>Tahun Masuk</label><input class="input" type="number" name="tahun_masuk" value="{{ request('tahun_masuk') }}" placeholder="2026"></div>
        <button class="btn light" type="submit">@icon('search') Filter</button>
    </form>

    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nama Lengkap</th><th>NIS / NISN</th><th>JK</th><th>Program</th><th>Kelas Aktif</th><th>Tahun Masuk</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($students as $s)
            @php($enr = $s->enrollments->first())
            <tr>
                <td><b>{{ $s->nama }}</b><div class="muted small">Wali: {{ $s->nama_wali ?: '-' }}</div></td>
                <td><b>{{ $s->nis }}</b><div class="muted small">NISN: {{ $s->nisn ?: '-' }}</div></td>
                <td>{{ $s->jenis_kelamin }}</td>
                <td><span class="badge blue">{{ $s->program->nama }}</span></td>
                <td>{{ $enr?->classroom?->nama ?? 'Belum ditempatkan' }}</td>
                <td>{{ $s->tahun_masuk ?: '-' }}</td>
                <td><span class="badge {{ $s->status === 'aktif' ? 'green' : ($s->status === 'lulus' ? 'purple' : '') }}">{{ \App\Models\Student::STATUS[$s->status] ?? $s->status }}</span></td>
                <td class="actions">
                    <a class="btn light sm" href="{{ route('master.students.show', $s) }}">@icon('eye') Detail</a>
                    @perm('students.manage')
                        <a class="btn light sm" href="{{ route('master.students.edit', $s) }}">@icon('edit')</a>
                        <form method="POST" action="{{ route('master.students.destroy', $s) }}" style="display:inline" data-confirm="Hapus peserta didik {{ $s->nama }}?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                    @endperm
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty icon="users" text="Belum ada data peserta didik." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $students->links() }}
</div>
@endsection
