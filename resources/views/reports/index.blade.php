@extends('layouts.app')
@section('title', 'Rapor Kelas')
@section('content')
<div class="page-head">
    <div><h1>Rapor Kelas</h1><p>Pantau kelengkapan, ajukan, sahkan, dan cetak rapor per kelas dan semester.</p></div>
    @if($class && $semester)
    <div class="btn-group">
        <a class="btn green" href="{{ route('academic.recap.excel', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('download') Leger Excel</a>
        <a class="btn sky" href="{{ route('academic.recap.pdf', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('download') Leger PDF</a>
        <a class="btn light" target="_blank" href="{{ route('reports.class.print', [$class, 'semester_id' => $semester->id]) }}">@icon('print') Cetak Massal</a>
        <a class="btn light" href="{{ route('reports.class.pdf', [$class, 'semester_id' => $semester->id]) }}">@icon('download') PDF Gabungan</a>
        <a class="btn light" href="{{ route('reports.class.zip', [$class, 'semester_id' => $semester->id]) }}">@icon('archive') ZIP</a>
    </div>
    @endif
</div>

<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label>
            <select class="input" name="semester_id" data-autosubmit>
                @foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}{{ $s->is_active ? ' (aktif)' : '' }}</option>@endforeach
            </select></div>
        <div class="field"><label>Kelas</label>
            <select class="input" name="kelas_id" data-autosubmit>
                @foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach
            </select></div>
        <div class="field"><label>Status</label>
            <select class="input" name="status" data-autosubmit><option value="">Semua status</option>
                @foreach(config('erapor.status_rapor') as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v[0] }}</option>@endforeach
            </select></div>
    </form>

    @if(!$class)
        <x-empty icon="class" text="Belum ada kelas pada tahun ajaran ini yang dapat Anda akses." />
    @else
        <div class="card-b" style="border-bottom:1px solid var(--line)">
            <div class="grid g4">
                <div><div class="muted small">Wali Kelas</div><b>{{ $class->teacher?->nama ?? 'Belum ditetapkan' }}</b></div>
                <div><div class="muted small">Peserta didik · Mata pelajaran</div><b>{{ $progress['siswa'] }} · {{ $progress['mapel'] }}</b></div>
                <div><div class="muted small">Progres nilai lengkap ({{ $progress['lengkap'] }}/{{ $progress['diharapkan'] }})</div>
                    <div class="progress" style="margin-top:8px"><i style="width:{{ $progress['persen'] }}%"></i></div><b>{{ $progress['persen'] }}%</b></div>
                <div><div class="muted small">Titimangsa rapor</div>
                    @if($titimangsa)<b>{{ $titimangsa->titimangsa }}</b>@else<span class="badge red">Belum diatur</span>@endif
                    @perm('reports.settings')<a class="small" href="{{ route('reports.settings') }}"> ubah</a>@endperm</div>
            </div>
            <div class="flex gap wrap mt">
                @foreach(config('erapor.status_rapor') as $k => $v)
                    @if(($statusCount[$k] ?? 0) > 0)<span class="badge {{ $v[1] }}">{{ $v[0] }}: {{ $statusCount[$k] }}</span>@endif
                @endforeach
            </div>
        </div>
        @if($cards->isNotEmpty() && ($semester))
        <div class="card-b" style="padding:12px 18px;border-bottom:1px solid var(--line)">
            <div class="btn-group">
                @perm('reports.prepare')
                <form method="POST" action="{{ route('reports.class.submit-all', $class) }}" data-confirm="Tanda tangani dan ajukan semua rapor yang sudah lengkap dalam kelas ini kepada Kepala PKBM? Rapor yang belum lengkap akan dilewati." data-confirm-title="Ajukan Semua Rapor" data-confirm-class="sky" data-confirm-ok="Ya, ajukan">@csrf<input type="hidden" name="semester_id" value="{{ $semester->id }}"><button class="btn sky" type="submit">@icon('file-check') Ajukan Semua ke Kepala PKBM</button></form>
                @endperm
                @perm('reports.approve')
                <form method="POST" action="{{ route('reports.class.approve-all', $class) }}" data-confirm="Sahkan semua rapor berstatus 'Menunggu Pengesahan' pada kelas ini dengan tanda tangan digital Anda? Setiap rapor akan diterbitkan dengan QR Code dan dikunci." data-confirm-title="Sahkan Semua Rapor" data-confirm-class="green" data-confirm-ok="Ya, sahkan">@csrf<input type="hidden" name="semester_id" value="{{ $semester->id }}"><button class="btn green" type="submit">@icon('check-circle') Sahkan Semua yang Menunggu</button></form>
                @endperm
            </div>
        </div>
        @endif
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>No</th><th>Nama Peserta Didik</th><th>NIS</th><th>Kelengkapan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($cards as $i => $c)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><b>{{ $c->student->nama }}</b></td>
                        <td>{{ $c->student->nis }}</td>
                        <td>
                            @if($c->kelengkapan)
                                @if($c->kelengkapan['ok'])<span class="badge green">Lengkap</span>
                                @else<span class="badge amber" title="{{ implode(' ', $c->kelengkapan['missing']) }}">Nilai {{ $c->kelengkapan['mapel_lengkap'] }}/{{ $c->kelengkapan['mapel_total'] }}</span>
                                    <div class="muted small">{{ count($c->kelengkapan['missing']) }} bagian belum lengkap</div>@endif
                            @else<span class="badge green">Final</span>@endif
                        </td>
                        <td><x-status-badge :status="$c->status" /></td>
                        <td class="actions">
                            <a class="btn light sm" href="{{ route('reports.show', $c) }}">@icon('eye') Pratinjau</a>
                            <a class="btn light sm" href="{{ route('reports.pdf', $c) }}">@icon('download') PDF</a>
                            <a class="btn light sm" target="_blank" href="{{ route('reports.print', $c) }}">@icon('print')</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="users" text="Belum ada peserta didik pada kelas ini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
