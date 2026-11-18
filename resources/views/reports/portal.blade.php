@extends('layouts.app')
@section('title', 'Rapor Saya')
@section('content')
<div class="page-head"><div><h1>Rapor Saya</h1><p>Rapor semester yang telah disahkan Kepala PKBM, termasuk riwayat semester sebelumnya.</p></div></div>
<div class="card">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Tahun Ajaran</th><th>Semester</th><th>Program / Kelas</th><th>Tanggal Terbit</th><th></th></tr></thead>
        <tbody>
        @forelse($cards as $c)
            <tr>
                <td><b>{{ $c->semester->academicYear->nama }}</b></td>
                <td>{{ ucfirst($c->semester->jenis) }}</td>
                <td>{{ $c->classroom->program->nama }} Kelas {{ $c->classroom->tingkat }}</td>
                <td>{{ $c->approved_at?->translatedFormat('j F Y') }}</td>
                <td class="actions">
                    <a class="btn light sm" href="{{ route('reports.show', $c) }}">@icon('eye') Lihat Rapor</a>
                    <a class="btn sky sm" href="{{ route('reports.pdf', $c) }}">@icon('download') Unduh PDF</a>
                    <a class="btn light sm" target="_blank" href="{{ route('reports.print', $c) }}">@icon('print') Cetak</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty icon="file" text="Belum ada rapor yang disahkan." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
