<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — E-Raport {{ $school->nama_lembaga }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth">
    <section class="auth-hero">
        <div class="brand">
            @if($school->logo_path)<img src="{{ route('school.logo') }}" alt="Logo">@else<span class="brand-logo">PK</span>@endif
            <div><b>{{ $school->nama_lembaga }}</b><small>{{ $school->jenis_lembaga }}</small></div>
        </div>
        <div style="position:relative;z-index:1">
            <h1>SISTEM INFORMASI E-RAPORT {{ strtoupper($school->nama_lembaga) }}</h1>
            <p>Pengelolaan nilai, absensi, catatan wali kelas, pengesahan, dan penerbitan rapor digital Paket B dan Paket C.</p>
            <ul>
                <li>@icon('check-circle') Rapor digital PDF dengan QR Code verifikasi</li>
                <li>@icon('check-circle') Tanda tangan digital Guru/Wali Kelas dan Kepala PKBM</li>
                <li>@icon('check-circle') Arsip rapor dan riwayat kenaikan kelas</li>
            </ul>
        </div>
        <small style="position:relative;z-index:1;color:#93acd4">© {{ date('Y') }} {{ $school->nama_lembaga }}</small>
    </section>
    <section class="auth-form">
        <div class="auth-box">
            <h2>Selamat datang</h2>
            <p class="muted" style="margin:0 0 22px">Masuk menggunakan akun Anda.</p>
            @if(session('info'))<div class="alert info">@icon('info')<div>{{ session('info') }}</div></div>@endif
            <form method="POST" action="{{ route('login.attempt') }}" novalidate>
                @csrf
                <div class="field mb">
                    <label for="username">Nama pengguna / Email</label>
                    <input class="input @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus required>
                    @error('username')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field mb">
                    <label for="password">Kata sandi</label>
                    <div class="pw-wrap">
                        <input class="input @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="current-password" required>
                        <button type="button" data-pw-toggle aria-label="Tampilkan kata sandi">@icon('eye')</button>
                    </div>
                    @error('password')<div class="err">{{ $message }}</div>@enderror
                </div>
                <label class="check mb"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
                <button class="btn block" type="submit">Masuk</button>
            </form>
            <p class="muted small center mt-lg">Lupa kata sandi? Hubungi Operator PKBM untuk mengatur ulang.</p>
        </div>
    </section>
</div>
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
