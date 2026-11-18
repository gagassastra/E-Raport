@extends('layouts.app')
@section('title', 'Backup Database')
@section('content')
<div class="page-head">
    <div><h1>Backup & Pemeliharaan Database</h1><p>Buat dan unduh salinan cadangan (SQL) seluruh tabel database E-Raport.</p></div>
    <form method="POST" action="{{ route('admin.backups.store') }}">@csrf<button class="btn" type="submit">@icon('database') Buat Backup Sekarang</button></form>
</div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nama Berkas</th><th>Ukuran</th><th>Waktu Dibuat</th><th></th></tr></thead>
        <tbody>
        @forelse($files as $f)
            <tr>
                <td><b>{{ $f->name }}</b></td>
                <td>{{ $f->size }} KB</td>
                <td>{{ \Carbon\Carbon::createFromTimestamp($f->modified)->timezone(config('app.timezone'))->translatedFormat('j F Y H:i:s') }}</td>
                <td class="actions">
                    <a class="btn light sm" href="{{ route('admin.backups.download', $f->name) }}">@icon('download') Unduh SQL</a>
                    <form method="POST" action="{{ route('admin.backups.destroy', $f->name) }}" style="display:inline" data-confirm="Hapus berkas backup {{ $f->name }}?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><x-empty icon="database" text="Belum ada berkas backup. Klik 'Buat Backup Sekarang' untuk membuat cadangan." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
