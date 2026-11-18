@extends('layouts.app')
@section('title', 'Log Aktivitas Sistem')
@section('content')
<div class="page-head"><div><h1>Log Aktivitas Sistem (Audit Trail)</h1><p>Rekam jejak seluruh aktivitas login, pengelolaan nilai, pengajuan, pengesahan, hingga pembukaan kembali rapor.</p></div></div>
<div class="card">
    <form class="filters" method="GET">
        <div class="field search"><label>Cari Aktivitas</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Pengguna / Aksi / Deskripsi"></div>
        <div class="field"><label>Modul</label><select class="input" name="modul" data-autosubmit><option value="">Semua Modul</option>@foreach($moduls as $m)<option value="{{ $m }}" @selected(request('modul') === $m)>{{ ucfirst($m) }}</option>@endforeach</select></div>
        <button class="btn light" type="submit">@icon('search') Filter</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Aksi</th><th>Deskripsi</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            <tr>
                <td class="small muted nowrap">{{ $l->created_at?->translatedFormat('j M Y H:i:s') }}</td>
                <td><b>{{ $l->user_nama ?: 'Sistem/Tamu' }}</b></td>
                <td><span class="badge blue">{{ $l->modul ?: '-' }}</span></td>
                <td><code>{{ $l->aksi }}</code></td>
                <td>{{ $l->deskripsi }}</td>
                <td class="small muted">{{ $l->ip }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty icon="clock" text="Belum ada catatan aktivitas." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
