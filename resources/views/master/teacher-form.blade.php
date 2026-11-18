@extends('layouts.app')
@section('title', $teacher->exists ? 'Edit Guru / Wali Kelas' : 'Tambah Guru / Wali Kelas')
@section('content')
<div class="page-head">
    <div><h1>{{ $teacher->exists ? 'Edit Guru / Wali Kelas' : 'Tambah Guru / Wali Kelas' }}</h1><p>Akun login ini memiliki role Guru / Wali Kelas.</p></div>
    <a class="btn light" href="{{ route('master.teachers.index') }}">Kembali</a>
</div>
<div class="card"><div class="card-b">
    <form method="POST" action="{{ $teacher->exists ? route('master.teachers.update', $teacher) : route('master.teachers.store') }}">
        @csrf @if($teacher->exists) @method('PUT') @endif
        <div class="form-grid">
            <x-field name="nama" label="Nama Lengkap (beserta gelar)" :value="$teacher->nama" required />
            <x-field name="nip" label="NIP / NUPTK / Nomor Identitas (Jika Tersedia)" :value="$teacher->nip" />
            <x-field name="jenis_kelamin" type="select" label="Jenis Kelamin" required>
                <option value="L" @selected(old('jenis_kelamin', $teacher->jenis_kelamin) === 'L')>Laki-laki (L)</option>
                <option value="P" @selected(old('jenis_kelamin', $teacher->jenis_kelamin) === 'P')>Perempuan (P)</option>
            </x-field>
            <x-field name="jabatan" label="Jabatan" :value="$teacher->jabatan" placeholder="Guru / Wali Kelas" />
            <x-field name="telepon" label="Nomor Telepon" :value="$teacher->telepon" />
            <x-field name="email" type="email" label="Email" :value="$teacher->email" />
            <x-field name="alamat" type="textarea" label="Alamat" :value="$teacher->alamat" rows="2" full />
            <x-field name="username" label="Nama Pengguna (Akun Login)" :value="$teacher->user?->username" required />
            <x-field name="password" type="password" label="{{ $teacher->exists ? 'Kata Sandi Baru (Kosongkan jika tidak diubah)' : 'Kata Sandi Awal' }}" :required="!$teacher->exists" />
            <div class="field full">
                <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $teacher->is_active))> Status Aktif (Dapat login dan ditetapkan sebagai Wali Kelas)</label>
            </div>
        </div>
        <div class="mt"><button class="btn" type="submit">@icon('check') Simpan Data Guru</button></div>
    </form>
</div></div>
@endsection
