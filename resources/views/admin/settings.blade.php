@extends('layouts.app')
@section('title', 'Pengaturan Aplikasi')
@section('content')
<div class="page-head"><div><h1>Konfigurasi Sistem Aplikasi</h1><p>Pengaturan domain publik untuk QR Code verifikasi rapor dan opsi sistem lainnya.</p></div></div>
<div class="card" style="max-width:640px"><div class="card-b">
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        <div class="grid">
            <x-field name="verification_base_url" label="URL Domain Aplikasi (untuk QR Code Verifikasi Rapor)" :value="$verification_base_url" required hint="Gunakan domain resmi saat aplikasi di-hosting, mis. https://eraport.pkbmalqudwah.sch.id" />
            <x-field name="catatan_kaki_rapor" label="Catatan Kaki Tambahan pada Rapor (Opsional)" :value="$catatan_kaki_rapor" />
        </div>
        <button class="btn mt" type="submit">@icon('check') Simpan Konfigurasi</button>
    </form>
</div></div>
@endsection
