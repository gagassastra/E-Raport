@extends('layouts.app')
@section('title', 'Kelas Saya')
@section('content')
<div class="page-head">
    <div><h1>Kelas yang Diampu</h1><p>Daftar peserta didik, kelengkapan nilai seluruh mata pelajaran, absensi, catatan wali kelas, dan status rapor.</p></div>
    @if($class && $semester)
    <div class="btn-group">
        <a class="btn sky" href="{{ route('academic.scores.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('edit') Input Nilai</a>
        <a class="btn light" href="{{ route('academic.attendance.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('clipboard') Absensi</a>
        <a class="btn light" href="{{ route('academic.notes.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('pen') Catatan</a>
        <a class="btn light" href="{{ route('reports.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('file-check') Rapor Kelas</a>
    </div>
    @endif
</div>

<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label><select class="input" name="semester_id" data-autosubmit>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}{{ $s->is_active ? ' (aktif)' : '' }}</option>@endforeach</select></div>
        <div class="field"><label>Kelas</label><select class="input" name="kelas_id" data-autosubmit>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
    </form>

    @if(!$class)
        <x-empty icon="class" text="Anda belum ditugaskan sebagai Guru/Wali Kelas pada tahun ajaran ini." />
    @else
        <div class="card-b" style="border-bottom:1px solid var(--line)">
            <div class="grid g4">
                <div><div class="muted small">Kelas</div><b>{{ $class->nama }}</b><div class="small muted">{{ $class->program->nama }} ({{ $class->program->setara }})</div></div>
                <div><div class="muted small">Jumlah Peserta Didik</div><b>{{ $progress['siswa'] }} orang</b></div>
                <div><div class="muted small">Mata Pelajaran Kelas Ini</div><b>{{ $progress['mapel'] }} mata pelajaran</b></div>
                <div><div class="muted small">Progres Pengisian Nilai ({{ $progress['lengkap'] }}/{{ $progress['diharapkan'] }})</div>
                    <div class="progress" style="margin-top:6px"><i style="width:{{ $progress['persen'] }}%"></i></div><b>{{ $progress['persen'] }}%</b></div>
            </div>
            @if($subjects->isNotEmpty())
                <div class="mt"><span class="muted small">Pilih cepat mata pelajaran: </span>
                    @foreach($subjects as $sub)
                        <a class="badge blue" style="margin:2px" href="{{ route('academic.scores.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id, 'mapel_id' => $sub->id]) }}">{{ $sub->nama }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>No</th><th>Nama Peserta Didik</th><th>NIS / NISN</th><th>Nilai Lengkap</th><th>Absensi (S/I/A)</th><th>Catatan Wali</th><th>Status Rapor</th><th></th></tr></thead>
            <tbody>
            @forelse($students as $i => $st)
                @php($scCount = $scoreCountByStudent[$st->id] ?? 0)
                @php($att = $attByStudent->get($st->id))
                @php($note = $noteByStudent->get($st->id))
                @php($card = $cardByStudent->get($st->id))
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><b>{{ $st->nama }}</b><div class="muted small">{{ $st->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</div></td>
                    <td>{{ $st->nis }}<div class="muted small">{{ $st->nisn ?: '-' }}</div></td>
                    <td>
                        <span class="badge {{ $subjects->count() && $scCount === $subjects->count() ? 'green' : 'amber' }}">{{ $scCount }} / {{ $subjects->count() }} mapel</span>
                    </td>
                    <td>
                        @if($att)<span class="badge green">{{ $att->sakit }} / {{ $att->izin }} / {{ $att->alpa }}</span>@else<span class="badge amber">Belum diisi</span>@endif
                    </td>
                    <td>
                        @if($note && $note->isFilled())<span class="badge green">Terisi</span>@else<span class="badge amber">Belum diisi</span>@endif
                    </td>
                    <td>@if($card)<x-status-badge :status="$card->status" />@else<span class="badge">Belum Diisi</span>@endif</td>
                    <td class="actions">
                        <a class="btn light sm" href="{{ route('academic.notes.edit', [$st, 'kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">Catatan</a>
                        @if($card)<a class="btn light sm" href="{{ route('reports.show', $card) }}">@icon('eye') Rapor</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="users" text="Belum ada peserta didik di kelas ini." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    @endif
</div>
@endsection
