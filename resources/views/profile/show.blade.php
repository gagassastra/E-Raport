@extends('layouts.app')
@section('title', 'Profil Saya')
@section('content')
<div class="page-head"><div><h1>Profil Saya</h1><p>Informasi akun dan data pribadi.</p></div></div>
<div class="grid g-main">
    <div class="card">
        <div class="card-h"><h3>Data Akun</h3><span class="badge navy">{{ $user->role->nama }}</span></div>
        <div class="card-b">
        @if($user->hasRole('siswa'))
            @php($s = $user->student)
            <dl class="kv">
                <dt>Nama lengkap</dt><dd>{{ $s?->nama ?? $user->name }}</dd>
                <dt>NIS</dt><dd>{{ $s?->nis ?? '-' }}</dd>
                <dt>NISN</dt><dd>{{ $s?->nisn ?? '-' }}</dd>
                <dt>Jenis kelamin</dt><dd>{{ ($s?->jenis_kelamin ?? '') === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                <dt>Tempat, tanggal lahir</dt><dd>{{ $s?->tempat_lahir ?? '-' }}, {{ $s?->tanggal_lahir?->translatedFormat('j F Y') ?? '-' }}</dd>
                <dt>Agama</dt><dd>{{ $s?->agama ?? '-' }}</dd>
                <dt>Alamat</dt><dd>{{ $s?->alamat ?? '-' }}</dd>
                <dt>Orang tua/wali</dt><dd>{{ $s?->nama_wali ?? '-' }}</dd>
                <dt>Program</dt><dd>{{ $s?->program?->nama ?? '-' }}</dd>
                <dt>Kelas saat ini</dt><dd>{{ $enrollment ? $enrollment->classroom->nama : 'Belum ditempatkan' }}</dd>
            </dl>
            <p class="muted small mt">Perubahan data pribadi dilakukan oleh Operator PKBM.</p>
        @else
            <form method="POST" action="{{ route('profile.update') }}">@csrf @method('PUT')
                <div class="form-grid">
                    <x-field name="name" label="Nama lengkap" :value="$user->name" required />
                    <x-field name="username" label="Nama pengguna" :value="$user->username" disabled />
                    <x-field name="email" type="email" label="Email" :value="$user->email" />
                    <x-field name="phone" label="Nomor telepon" :value="$user->phone" />
                </div>
                <div class="mt"><button class="btn" type="submit">Simpan Profil</button></div>
            </form>
        @endif
        </div>
    </div>
    <div class="grid" style="align-content:start">
        <div class="card"><div class="card-b">
            <h3 class="mb">Keamanan</h3>
            <p class="muted small">Terakhir masuk: {{ $user->last_login_at?->translatedFormat('j F Y H:i') ?? '-' }}</p>
            <a class="btn light block" href="{{ route('profile.password') }}">@icon('key') Ganti Kata Sandi</a>
        </div></div>
        @perm('signature.manage')
        <div class="card"><div class="card-b">
            <h3 class="mb">Tanda Tangan Digital</h3>
            <p class="muted small">{{ $user->signature ? 'Tanda tangan sudah tersimpan.' : 'Belum ada tanda tangan.' }}</p>
            <a class="btn light block" href="{{ route('profile.signature') }}">@icon('pen') Kelola Tanda Tangan</a>
        </div></div>
        @endperm
    </div>
</div>
@endsection
