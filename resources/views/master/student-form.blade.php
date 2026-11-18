@extends('layouts.app')
@section('title', $student->exists ? 'Edit Peserta Didik' : 'Tambah Peserta Didik')
@section('content')
<div class="page-head">
    <div><h1>{{ $student->exists ? 'Edit Data Peserta Didik' : 'Tambah Peserta Didik Baru' }}</h1><p>Pastikan memilih program Paket B atau Paket C dengan benar.</p></div>
    <a class="btn light" href="{{ route('master.students.index') }}">Kembali</a>
</div>
<div class="card"><div class="card-b">
    <form method="POST" action="{{ $student->exists ? route('master.students.update', $student) : route('master.students.store') }}">
        @csrf @if($student->exists) @method('PUT') @endif
        <div class="form-grid">
            <x-field name="nis" label="NIS" :value="$student->nis" required />
            <x-field name="nisn" label="NISN" :value="$student->nisn" />
            <x-field name="nama" label="Nama Lengkap" :value="$student->nama" required />
            <x-field name="jenis_kelamin" type="select" label="Jenis Kelamin" required>
                <option value="L" @selected(old('jenis_kelamin', $student->jenis_kelamin) === 'L')>Laki-laki (L)</option>
                <option value="P" @selected(old('jenis_kelamin', $student->jenis_kelamin) === 'P')>Perempuan (P)</option>
            </x-field>
            <x-field name="tempat_lahir" label="Tempat Lahir" :value="$student->tempat_lahir" />
            <x-field name="tanggal_lahir" type="date" label="Tanggal Lahir" :value="$student->tanggal_lahir?->toDateString()" />
            <x-field name="agama" type="select" label="Agama">
                <option value="">— Pilih —</option>
                @foreach(config('erapor.agama') as $ag)<option value="{{ $ag }}" @selected(old('agama', $student->agama) === $ag)>{{ $ag }}</option>@endforeach
            </x-field>
            <x-field name="telepon" label="Nomor Telepon / HP" :value="$student->telepon" />
            <x-field name="nama_wali" label="Nama Orang Tua / Wali" :value="$student->nama_wali" />
            <x-field name="tahun_masuk" type="number" min="2000" max="2100" label="Tahun Masuk" :value="$student->tahun_masuk" />
            <x-field name="education_program_id" type="select" label="Program Pendidikan" required>
                @foreach($programs as $p)<option value="{{ $p->id }}" @selected(old('education_program_id', $student->education_program_id) == $p->id)>{{ $p->nama }} ({{ $p->setara }})</option>@endforeach
            </x-field>
            <x-field name="status" type="select" label="Status Peserta Didik" required>
                @foreach(\App\Models\Student::STATUS as $k => $lbl)<option value="{{ $k }}" @selected(old('status', $student->status) === $k)>{{ $lbl }}</option>@endforeach
            </x-field>
            <x-field name="alamat" type="textarea" label="Alamat Lengkap" :value="$student->alamat" rows="2" full />
            <x-field name="username" label="Nama Pengguna Login (Default: NIS)" :value="$student->user?->username ?? $student->nis" :required="$student->exists" />
            <x-field name="password" type="password" label="{{ $student->exists ? 'Kata Sandi Baru (Kosongkan jika tetap)' : 'Kata Sandi Awal (Kosongkan = sama dengan NIS)' }}" />
        </div>
        <div class="mt"><button class="btn" type="submit">@icon('check') Simpan Peserta Didik</button></div>
    </form>
</div></div>
@endsection
