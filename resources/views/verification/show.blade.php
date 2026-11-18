<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2a5b">
    <meta name="robots" content="noindex">
    <title>Verifikasi Tanda Tangan & Rapor</title>
    <link rel="icon" type="image/png" href="{{ route('school.logo') }}">
    <link rel="apple-touch-icon" href="{{ route('school.logo') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="verify">
    <div class="verify-banner">
        <img class="verify-logo" src="{{ route('school.logo') }}" alt="Logo">
        <div>
            <b>{{ $data['lembaga']['nama'] ?? 'PKBM AL-QUDWAH' }}</b>
            <small>Sistem Informasi Verifikasi E-Raport & Tanda Tangan Elektronik</small>
        </div>
    </div>
    @if(!$found)
        <div class="verify-status bad">@icon('x-circle', 'ic ic-lg')<h1>TIDAK VALID</h1><p>Kode verifikasi tidak ditemukan. Dokumen ini tidak dapat dinyatakan asli.</p></div>
    @else
        <div class="verify-status {{ $valid ? 'ok' : 'bad' }}">
            @icon($valid ? 'check-circle' : 'x-circle', 'ic ic-lg')
            <h1>{{ $valid ? 'VALID' : 'TIDAK VALID / BELUM DISAHKAN' }}</h1>
            <p>@if($valid)Tanda tangan elektronik dan dokumen rapor ini asli serta telah disahkan oleh {{ $data['lembaga']['nama'] }}.@else Dokumen rapor ini belum disahkan atau versinya sudah tidak berlaku.@endif</p>
        </div>

        {{-- Tampilan Tanda Tangan Asli saat QR di-scan HP --}}
        @php
            $signers = [
                'kepala' => [
                    'judul' => 'Tanda Tangan Kepala PKBM',
                    'jabatan' => $data['kepala']['jabatan'] ?? 'Kepala PKBM',
                    'nama' => $data['kepala']['nama'] ?? '-',
                    'identitas_label' => $data['kepala']['identitas_label'] ?? 'NIP/No. ID',
                    'identitas' => $data['kepala']['identitas'] ?? null,
                    'ttd' => $data['kepala']['ttd'] ?? null,
                    'waktu' => $data['kepala']['ditandatangani_at'] ?? null,
                ],
                'wali' => [
                    'judul' => 'Tanda Tangan Guru / Wali Kelas',
                    'jabatan' => 'Guru / Wali Kelas',
                    'nama' => $data['wali']['nama'] ?? '-',
                    'identitas_label' => $data['wali']['identitas_label'] ?? 'NIP/No. ID',
                    'identitas' => $data['wali']['identitas'] ?? null,
                    'ttd' => $data['wali']['ttd'] ?? null,
                    'waktu' => $data['wali']['ditandatangani_at'] ?? null,
                ],
            ];
            if (($penanda ?? null) === 'wali') {
                $signers = ['wali' => $signers['wali'], 'kepala' => $signers['kepala']];
            }
        @endphp

        <div class="grid g2 mb">
            @foreach($signers as $key => $sg)
                <div class="card" style="{{ ($penanda ?? null) === $key ? 'border:2px solid var(--navy)' : '' }}">
                    <div class="card-h">
                        <h3>{{ $sg['judul'] }}</h3>
                        @if(($penanda ?? null) === $key)<span class="badge navy">Dipindai</span>@endif
                    </div>
                    <div class="card-b center">
                        <div class="sig-preview" style="margin:0 auto 10px;min-height:110px;display:flex;align-items:center;justify-content:center">
                            @if(!empty($sg['ttd']))
                                <img src="{{ $sg['ttd'] }}" alt="{{ $sg['judul'] }}" style="max-height:100px;max-width:100%">
                            @else
                                <span class="muted small">Belum ada gambar tanda tangan</span>
                            @endif
                        </div>
                        <div style="font-weight:700;color:var(--navy);text-decoration:underline">{{ $sg['nama'] }}</div>
                        <div class="small muted">{{ $sg['jabatan'] }}</div>
                        @if(!empty($sg['identitas']))<div class="small muted">{{ $sg['identitas_label'] }}: {{ $sg['identitas'] }}</div>@endif
                        @if(!empty($sg['waktu']))
                            <div class="mt"><span class="badge green">Ditandatangani {{ \Carbon\Carbon::parse($sg['waktu'])->timezone(config('app.timezone'))->translatedFormat('j M Y H:i') }} WIB</span></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card"><div class="card-b">
            <h3 class="mb">Informasi Dokumen Rapor</h3>
            <dl class="kv">
                <dt>Lembaga</dt><dd>{{ $data['lembaga']['nama'] }}</dd>
                <dt>Peserta didik</dt><dd>{{ $nama }}</dd>
                <dt>Program / Kelas</dt><dd>{{ $data['kelas']['program'] }} — Kelas {{ $data['kelas']['tingkat'] }}</dd>
                <dt>Tahun ajaran</dt><dd>{{ $data['kelas']['tahun_ajaran'] }}</dd>
                <dt>Semester</dt><dd>{{ $data['kelas']['semester'] }}</dd>
                <dt>Tanggal penerbitan</dt><dd>{{ $data['titimangsa']['teks'] ?? '-' }}</dd>
                <dt>Guru/Wali Kelas</dt><dd>{{ $data['wali']['nama'] }} <span class="badge green">Menandatangani</span></dd>
                <dt>Kepala PKBM</dt><dd>{{ $data['kepala']['nama'] }} <span class="badge {{ $valid ? 'green' : 'red' }}">{{ $valid ? 'Mengesahkan' : 'Pengesahan tidak berlaku' }}</span></dd>
                @if($version)
                    <dt>Versi dokumen</dt><dd>{{ $version->versi }}</dd>
                    <dt>Sidik PDF (SHA-256)</dt><dd class="small" style="word-break:break-all">{{ $version->pdf_hash }}</dd>
                @endif
            </dl>
        </div></div>
    @endif
    <p class="center muted small mt">Sistem Informasi E-Raport PKBM AL-QUDWAH</p>
</div>
</body>
</html>
