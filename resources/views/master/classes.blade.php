@extends('layouts.app')
@section('title', 'Rombongan Belajar / Kelas')
@section('content')
<div class="page-head"><div><h1>Data Rombongan Belajar (Kelas)</h1><p>Kelola kelas Paket B (Kelas 7, 8, 9) dan Paket C (Kelas 10, 11, 12), tetapkan Guru/Wali Kelas, serta atur pembagian kelas peserta didik.</p></div></div>

<div class="grid {{ auth()->user()->hasPermission('classes.manage') ? 'g-main' : '' }}" style="align-items:start">
    <div class="card">
        <form class="filters" method="GET">
            <div class="field"><label>Tahun Ajaran</label>
                <select class="input" name="academic_year_id" data-autosubmit>
                    <option value="">Semua Tahun Ajaran</option>
                    @foreach($years as $y)<option value="{{ $y->id }}" @selected($yearFilter == $y->id)>{{ $y->nama }}{{ $y->is_active ? ' (Aktif)' : '' }}</option>@endforeach
                </select></div>
            <div class="field"><label>Program</label>
                <select class="input" name="program_id" data-autosubmit>
                    <option value="">Semua Program</option>
                    @foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->nama }}</option>@endforeach
                </select></div>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Nama Kelas</th><th>Program / Tingkat</th><th>Tahun Ajaran</th><th>Kurikulum</th><th>Guru / Wali Kelas</th><th class="c">Peserta Didik</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($classes as $c)
                <tr>
                    <td><b>{{ $c->nama }}</b></td>
                    <td><span class="badge blue">{{ $c->program->nama }} — Kelas {{ $c->tingkat }}</span></td>
                    <td>{{ $c->academicYear->nama }}</td>
                    <td class="small">{{ $c->curriculum->nama }}</td>
                    <td>
                        @if($c->teacher)<b>{{ $c->teacher->nama }}</b>@else<span class="badge amber">Belum ditetapkan</span>@endif
                    </td>
                    <td class="c"><b>{{ $c->siswa_aktif_count }}</b></td>
                    <td><span class="badge {{ $c->status === 'aktif' ? 'green' : '' }}">{{ ucfirst($c->status) }}</span></td>
                    <td class="actions">
                        <a class="btn sky sm" href="{{ route('master.classes.show', $c) }}">@icon('users') Anggota & Wali</a>
                        @perm('classes.manage')
                            <a class="btn light sm" href="{{ route('master.classes.index', ['academic_year_id' => $c->academic_year_id, 'edit' => $c->id]) }}">@icon('edit')</a>
                            @if($c->siswa_aktif_count === 0)
                                <form method="POST" action="{{ route('master.classes.destroy', $c) }}" style="display:inline" data-confirm="Hapus kelas {{ $c->nama }}?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                            @endif
                        @endperm
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="class" text="Belum ada rombongan belajar pada filter ini." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @perm('classes.manage')
    <div class="card">
        <div class="card-h"><h3>{{ $editing ? 'Edit Kelas' : 'Buat Kelas Baru' }}</h3></div>
        <div class="card-b">
            <form method="POST" action="{{ $editing ? route('master.classes.update', $editing) : route('master.classes.store') }}">
                @csrf @if($editing) @method('PUT') @endif
                <div class="grid">
                    <div class="field">
                        <label>Program Pendidikan <span class="req">*</span></label>
                        <select class="input" name="education_program_id" data-tingkat-target="#f-tingkat-kelas" required>
                            <option value="">— Pilih Program —</option>
                            @foreach($programs as $p)
                                <option value="{{ $p->id }}" data-awal="{{ $p->tingkat_awal }}" data-akhir="{{ $p->tingkat_akhir }}" @selected(old('education_program_id', $editing?->education_program_id) == $p->id)>{{ $p->nama }} ({{ $p->setara }})</option>
                            @endforeach
                        </select>
                        @error('education_program_id')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label>Tingkat Kelas <span class="req">*</span></label>
                        <select class="input" id="f-tingkat-kelas" name="tingkat" data-selected="{{ old('tingkat', $editing?->tingkat) }}" required></select>
                        @error('tingkat')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <x-field name="paralel" label="Kode Rombel Paralel (Opsional)" :value="$editing?->paralel" placeholder="Kosongkan atau isi A, B, dst." />
                    <x-field name="academic_year_id" type="select" label="Tahun Ajaran" required>
                        @foreach($years as $y)<option value="{{ $y->id }}" @selected(old('academic_year_id', $editing?->academic_year_id ?? $yearFilter) == $y->id)>{{ $y->nama }}{{ $y->is_active ? ' (Aktif)' : '' }}</option>@endforeach
                    </x-field>
                    <x-field name="curriculum_id" type="select" label="Kurikulum" required>
                        @foreach($curricula as $c)<option value="{{ $c->id }}" @selected(old('curriculum_id', $editing?->curriculum_id) == $c->id)>{{ $c->nama }}</option>@endforeach
                    </x-field>
                    <x-field name="teacher_id" type="select" label="Guru / Wali Kelas">
                        <option value="">— Pilih Guru / Wali Kelas —</option>
                        @foreach($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id', $editing?->teacher_id) == $t->id)>{{ $t->nama }}</option>@endforeach
                    </x-field>
                    <x-field name="nama" label="Nama Kelas (Otomatis jika dikosongkan)" :value="$editing?->nama" placeholder="Contoh: Paket B Kelas 7 Tahun Ajaran 2026/2027" />
                    <x-field name="status" type="select" label="Status Kelas" required>
                        <option value="aktif" @selected(old('status', $editing?->status ?? 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $editing?->status) === 'nonaktif')>Nonaktif</option>
                        <option value="arsip" @selected(old('status', $editing?->status) === 'arsip')>Arsip</option>
                    </x-field>
                </div>
                <div class="btn-group mt">
                    <button class="btn" type="submit">@icon('check') {{ $editing ? 'Simpan Perubahan' : 'Buat Kelas' }}</button>
                    @if($editing)<a class="btn light" href="{{ route('master.classes.index') }}">Batal</a>@endif
                </div>
            </form>
        </div>
    </div>
    @endperm
</div>
@endsection
