@extends('layouts.app')
@section('title', 'Arsip Rapor')
@section('content')
<div class="page-head"><div><h1>Arsip Rapor</h1><p>Rapor semester sebelumnya yang telah disahkan.</p></div></div>
<div class="card">
    <form class="filters" method="GET">
        <div class="field search"><label>Cari peserta didik</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Nama / NIS / NISN"></div>
        <div class="field"><label>Tahun ajaran</label><select class="input" name="tahun"><option value="">Semua</option>@foreach($years as $y)<option value="{{ $y->id }}" @selected(request('tahun') == $y->id)>{{ $y->nama }}</option>@endforeach</select></div>
        <div class="field"><label>Semester</label><select class="input" name="semester"><option value="">Semua</option><option value="ganjil" @selected(request('semester') === 'ganjil')>Ganjil</option><option value="genap" @selected(request('semester') === 'genap')>Genap</option></select></div>
        <div class="field"><label>Program</label><select class="input" name="program"><option value="">Semua</option>@foreach($programs as $p)<option value="{{ $p->id }}" @selected(request('program') == $p->id)>{{ $p->nama }}</option>@endforeach</select></div>
        <button class="btn" type="submit">@icon('search') Cari</button>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Peserta Didik</th><th>Kelas</th><th>Tahun Ajaran</th><th>Semester</th><th>Versi</th><th>Disahkan</th><th></th></tr></thead>
        <tbody>
        @forelse($cards as $c)
            <tr>
                <td><b>{{ $c->student->nama }}</b><div class="muted small">{{ $c->student->nis }}</div></td>
                <td>{{ $c->classroom->program->nama }} Kelas {{ $c->classroom->tingkat }}</td>
                <td>{{ $c->semester->academicYear->nama }}</td>
                <td>{{ ucfirst($c->semester->jenis) }}</td>
                <td>v{{ $c->versi_aktif }}</td>
                <td>{{ $c->approved_at?->translatedFormat('j M Y') }}</td>
                <td class="actions"><a class="btn light sm" href="{{ route('reports.show', $c) }}">@icon('eye') Lihat</a> <a class="btn light sm" href="{{ route('reports.pdf', $c) }}">@icon('download') PDF</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty icon="archive" text="Belum ada arsip rapor yang disahkan." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $cards->links() }}
</div>
@endsection
