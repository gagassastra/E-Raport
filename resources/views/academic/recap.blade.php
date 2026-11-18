@extends('layouts.app')
@section('title', 'Rekap Nilai')
@section('content')
<div class="page-head"><div><h1>Rekap Nilai Seluruh Kelas</h1><p>Ringkasan progres pengisian dan matriks nilai akhir setiap kelas.</p></div></div>

<div class="card mb">
    <div class="card-h"><h3>Ringkasan Seluruh Kelas ({{ $semester?->label ?? '-' }})</h3></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Kelas</th><th>Program</th><th>Wali Kelas</th><th class="c">Peserta Didik</th><th class="c">Mapel</th><th>Progres Nilai</th><th class="c">Rata-rata</th><th></th></tr></thead>
        <tbody>
        @forelse($classRecap as $r)
            <tr>
                <td><b>{{ $r->class->nama }}</b></td>
                <td>{{ $r->class->program->nama }}</td>
                <td>{{ $r->class->teacher?->nama ?? '-' }}</td>
                <td class="c">{{ $r->progress['siswa'] }}</td>
                <td class="c">{{ $r->progress['mapel'] }}</td>
                <td style="min-width:160px"><div class="progress"><i style="width:{{ $r->progress['persen'] }}%"></i></div><span class="small muted">{{ $r->progress['lengkap'] }}/{{ $r->progress['diharapkan'] }} ({{ $r->progress['persen'] }}%)</span></td>
                <td class="c"><b>{{ $r->rata_rata !== null ? number_format($r->rata_rata, 1, ',', '') : '-' }}</b></td>
                <td class="actions"><a class="btn light sm" href="{{ route('academic.recap', ['semester_id' => $semester->id, 'kelas_id' => $r->class->id]) }}">Lihat Leger</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty icon="chart" text="Belum ada kelas pada semester ini." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label><select class="input" name="semester_id" data-autosubmit>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}</option>@endforeach</select></div>
        <div class="field"><label>Kelas (Leger Nilai Akhir)</label><select class="input" name="kelas_id" data-autosubmit>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
    </form>
    @if($class && $students->isNotEmpty())
        <div class="table-wrap"><table class="table">
            <thead><tr><th>No</th><th>Nama Peserta Didik</th><th>NIS</th>
                @foreach($subjects as $sub)<th class="c" title="{{ $sub->nama }}">{{ $sub->kode }}</th>@endforeach
                <th class="c">Rata-rata</th>
            </tr></thead>
            <tbody>
            @foreach($students as $i => $st)
                @php($vals = [])
                <tr>
                    <td>{{ $i + 1 }}</td><td><b>{{ $st->nama }}</b></td><td>{{ $st->nis }}</td>
                    @foreach($subjects as $sub)
                        @php($v = $matrix[$st->id][$sub->id] ?? null)
                        @if($v !== null) @php($vals[] = $v) @endif
                        <td class="c">{{ $v ?? '-' }}</td>
                    @endforeach
                    <td class="c"><b>{{ count($vals) ? number_format(array_sum($vals) / count($vals), 1, ',', '') : '-' }}</b></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @else
        <x-empty icon="users" text="Pilih kelas yang memiliki peserta didik untuk melihat leger nilai akhir." />
    @endif
</div>
@endsection
