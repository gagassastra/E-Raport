@extends('layouts.app')
@section('title', 'Mata Pelajaran')
@section('content')
<div class="page-head"><div><h1>Data Mata Pelajaran</h1><p>Mata pelajaran diatur berdasarkan program pendidikan (Paket B / Paket C), tingkat kelas, dan kurikulum.</p></div></div>

<div class="grid g-main" style="align-items:start">
    <div class="card">
        <form class="filters" method="GET">
            <div class="field"><label>Program</label>
                <select class="input" name="program_id" data-autosubmit><option value="">Semua Program</option>
                    @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->nama }}</option>@endforeach
                </select></div>
            <div class="field"><label>Tingkat</label>
                <select class="input" name="tingkat" data-autosubmit><option value="">Semua Tingkat</option>
                    @foreach([7,8,9,10,11,12] as $t)<option value="{{ $t }}" @selected(request('tingkat') == $t)>Kelas {{ $t }}</option>@endforeach
                </select></div>
            <div class="field"><label>Kurikulum</label>
                <select class="input" name="curriculum_id" data-autosubmit><option value="">Semua Kurikulum</option>
                    @foreach($curricula as $c)<option value="{{ $c->id }}" @selected(request('curriculum_id') == $c->id)>{{ $c->nama }}</option>@endforeach
                </select></div>
            <div class="field search"><label>Cari</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Kode / Nama mapel"></div>
            <button class="btn light" type="submit">@icon('search')</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th class="c">Urut</th><th>Kode</th><th>Nama Mata Pelajaran</th><th>Program / Tingkat</th><th>Kurikulum</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($subjects as $s)
                <tr>
                    <td class="c">{{ $s->urutan }}</td>
                    <td><b>{{ $s->kode }}</b></td>
                    <td><b>{{ $s->nama }}</b></td>
                    <td><span class="badge blue">{{ $s->program->nama }} — Kelas {{ $s->tingkat }}</span></td>
                    <td class="small">{{ $s->curriculum->kode }}</td>
                    <td><span class="badge {{ $s->is_active ? 'green' : '' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="actions">
                        <a class="btn light sm" href="{{ route('master.subjects.index', array_merge(request()->query(), ['edit' => $s->id])) }}">@icon('edit')</a>
                        <form method="POST" action="{{ route('master.subjects.destroy', $s) }}" style="display:inline" data-confirm="Hapus mata pelajaran {{ $s->nama }} (Kelas {{ $s->tingkat }})?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="book" text="Belum ada mata pelajaran untuk filter ini." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $subjects->links() }}
    </div>

    <div class="card">
        <div class="card-h"><h3>{{ $editing ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran' }}</h3></div>
        <div class="card-b">
            <form method="POST" action="{{ $editing ? route('master.subjects.update', $editing) : route('master.subjects.store') }}">
                @csrf @if($editing) @method('PUT') @endif
                <div class="grid">
                    <div class="field">
                        <label>Program Pendidikan <span class="req">*</span></label>
                        <select class="input" name="education_program_id" data-tingkat-target="#f-tingkat-mapel" required>
                            <option value="">— Pilih Program —</option>
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}" data-awal="{{ $p->tingkat_awal }}" data-akhir="{{ $p->tingkat_akhir }}" @selected(old('education_program_id', $editing?->education_program_id ?? request('program_id')) == $p->id)>{{ $p->nama }} ({{ $p->setara }})</option>
                            @endforeach
                        </select>
                        @error('education_program_id')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label>Tingkat Kelas <span class="req">*</span></label>
                        <select class="input" id="f-tingkat-mapel" name="tingkat" data-selected="{{ old('tingkat', $editing?->tingkat ?? request('tingkat')) }}" required></select>
                        @error('tingkat')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <x-field name="curriculum_id" type="select" label="Kurikulum" required>
                        @foreach($curricula as $c)<option value="{{ $c->id }}" @selected(old('curriculum_id', $editing?->curriculum_id) == $c->id)>{{ $c->nama }}</option>@endforeach
                    </x-field>
                    <x-field name="kode" label="Kode Mata Pelajaran" :value="$editing?->kode" placeholder="mis. PAI, BIND, MTK" required />
                    <x-field name="nama" label="Nama Mata Pelajaran" :value="$editing?->nama" placeholder="mis. Matematika" required />
                    <x-field name="urutan" type="number" min="1" max="999" label="Urutan di Rapor" :value="$editing?->urutan ?? 1" required />
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing?->is_active ?? true))> Mata Pelajaran Aktif</label>
                </div>
                <div class="btn-group mt">
                    <button class="btn" type="submit">@icon('check') {{ $editing ? 'Simpan Perubahan' : 'Tambah Mapel' }}</button>
                    @if($editing)<a class="btn light" href="{{ route('master.subjects.index') }}">Batal</a>@endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
