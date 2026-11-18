@extends('layouts.app')
@section('title', 'Ganti Kata Sandi')
@section('content')
<div class="page-head"><div><h1>Ganti Kata Sandi</h1><p>Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</p></div></div>
<div class="card" style="max-width:520px"><div class="card-b">
    <form method="POST" action="{{ route('profile.password.update') }}">@csrf @method('PUT')
        <div class="grid">
            <x-field name="password_lama" type="password" label="Kata sandi saat ini" required autocomplete="current-password" />
            <x-field name="password" type="password" label="Kata sandi baru" required autocomplete="new-password" />
            <x-field name="password_confirmation" type="password" label="Ulangi kata sandi baru" required autocomplete="new-password" />
        </div>
        <div class="mt"><button class="btn" type="submit">Simpan Kata Sandi</button></div>
    </form>
</div></div>
@endsection
