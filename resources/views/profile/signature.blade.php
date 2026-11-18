@extends('layouts.app')
@section('title', 'Tanda Tangan Digital')
@section('content')
<div class="page-head"><div><h1>Tanda Tangan Digital</h1><p>Gambar atau unggah tanda tangan Anda di bawah ini. Saat rapor dicetak atau diunduh sebagai PDF A4, tanda tangan Anda ditampilkan dalam bentuk <b>QR Code</b>; ketika QR Code tersebut di-scan dengan HP, halaman verifikasi akan menampilkan <b>gambar tanda tangan Anda</b> ini.</p></div></div>
<div class="grid g2">
    <div class="card"><div class="card-h"><h3>Tanda Tangan Saat Ini (Tampil Saat QR Di-scan HP)</h3></div><div class="card-b">
        @if($signature)
            <div class="sig-preview"><img src="{{ route('profile.signature.image') }}?v={{ $signature->updated_at->timestamp }}" alt="Tanda tangan"></div>
            <p class="muted small mt">Diperbarui {{ $signature->updated_at->translatedFormat('j F Y H:i') }} · Sidik {{ substr($signature->hash, 0, 12) }}…</p>
            <form method="POST" action="{{ route('profile.signature.destroy') }}" data-confirm="Hapus tanda tangan digital Anda?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash') Hapus Tanda Tangan</button></form>
        @else
            <x-empty icon="pen" text="Belum ada tanda tangan tersimpan. Silakan gambar pada kanvas atau unggah berkas tanda tangan di sebelah kanan." />
        @endif
    </div></div>
    <div class="card"><div class="card-h"><h3>Gambar / Unggah Tanda Tangan</h3></div><div class="card-b">
        <form method="POST" action="{{ route('profile.signature.store') }}" enctype="multipart/form-data">@csrf
            <input type="hidden" name="svg" value="">
            <p class="small muted" style="margin-top:0">Gambar tanda tangan dengan mouse atau layar sentuh pada kotak di bawah:</p>
            <div data-signature-pad><canvas class="sig-pad"></canvas><div class="mt"><button class="btn light sm" type="button" data-sig-clear data-noload="1">@icon('refresh') Hapus Coretan</button></div></div>
            @error('svg')<div class="err">{{ $message }}</div>@enderror
            <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
            <div class="field">
                <label>Atau unggah gambar tanda tangan (PNG/JPG, maks. 1 MB)</label>
                <input class="input" type="file" name="file" accept="image/png,image/jpeg">
                @error('file')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="mt"><button class="btn" type="submit">@icon('check') Simpan Tanda Tangan</button></div>
        </form>
    </div></div>
</div>
@endsection
