@extends('layouts.app')
@section('title', ($isGuru ?? false) ? 'Leger Nilai Kelas' : 'Rekap & Leger Nilai')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ ($isGuru ?? false) ? 'Leger Nilai Kelas Saya' : 'Rekap & Leger Nilai Kelas' }}</h1>
        <p>Laporan rekapitulasi (leger) nilai akhir seluruh mata pelajaran, jumlah nilai, rata-rata, peringkat, dan absensi peserta didik.</p>
    </div>
    @if($class && $semester && $rows->isNotEmpty())
        <div class="btn-group">
            <a class="btn green" href="{{ route('academic.recap.excel', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">@icon('download') Unduh Leger Excel (.xlsx)</a>
            <a class="btn sky" href="{{ route('academic.recap.pdf', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">@icon('download') Unduh Leger PDF</a>
            <a class="btn light" target="_blank" href="{{ route('academic.recap.print', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">@icon('print') Cetak Leger</a>
        </div>
    @endif
</div>

@if($classRecap->count() > 1 || !($isGuru ?? false))
<div class="card mb">
    <div class="card-h"><h3>Ringkasan Kelas ({{ $semester?->label ?? '-' }})</h3></div>
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
                <td class="actions">
                    <a class="btn light sm" href="{{ route('academic.recap', ['semester_id' => $semester->id, 'kelas_id' => $r->class->id]) }}">Lihat Leger</a>
                    <a class="btn green sm" href="{{ route('academic.recap.excel', ['semester_id' => $semester->id, 'kelas_id' => $r->class->id]) }}" title="Unduh Leger Excel">@icon('download') Excel</a>
                    <a class="btn sky sm" href="{{ route('academic.recap.pdf', ['semester_id' => $semester->id, 'kelas_id' => $r->class->id]) }}" title="Unduh Leger PDF">@icon('download') PDF</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty icon="chart" text="Belum ada kelas pada semester ini." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endif

<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label><select class="input" name="semester_id" data-autosubmit>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}{{ $s->is_active ? ' (aktif)' : '' }}</option>@endforeach</select></div>
        <div class="field"><label>Kelas (Leger Nilai Akhir)</label><select class="input" name="kelas_id" data-autosubmit>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
        @if($class && $semester && $rows->isNotEmpty())
            <div class="grow"></div>
            <div class="btn-group">
                <a class="btn green sm" href="{{ route('academic.recap.excel', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">@icon('download') Unduh Excel (.xlsx)</a>
                <a class="btn sky sm" href="{{ route('academic.recap.pdf', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">@icon('download') Unduh PDF</a>
                <a class="btn light sm" target="_blank" href="{{ route('academic.recap.print', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">@icon('print') Cetak</a>
            </div>
        @endif
    </form>
    @if($class && $rows->isNotEmpty())
        <div class="table-wrap"><table class="table">
            <thead><tr>
                <th>No</th>
                <th>Nama Peserta Didik</th>
                <th>NIS / NISN</th>
                <th class="c">L/P</th>
                @foreach($subjects as $sub)<th class="c" title="{{ $sub->nama }}">{{ $sub->kode }}</th>@endforeach
                <th class="c">Jumlah</th>
                <th class="c">Rata-rata</th>
                <th class="c">Peringkat</th>
                <th class="c" title="Sakit / Izin / Alpa">S / I / A</th>
            </tr></thead>
            <tbody>
            @foreach($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><b>{{ $r->student->nama }}</b></td>
                    <td>{{ $r->student->nis }}<div class="muted small">{{ $r->student->nisn ?: '-' }}</div></td>
                    <td class="c">{{ $r->student->jenis_kelamin }}</td>
                    @foreach($subjects as $sub)
                        @php($v = $r->scores[$sub->id] ?? null)
                        <td class="c">{{ $v ?? '-' }}</td>
                    @endforeach
                    <td class="c"><b>{{ $r->jumlah ?? '-' }}</b></td>
                    <td class="c"><b>{{ $r->rata_rata !== null ? number_format($r->rata_rata, 1, ',', '') : '-' }}</b></td>
                    <td class="c">
                        @if($r->peringkat !== null)
                            <span class="badge {{ $r->peringkat <= 3 ? 'green' : 'blue' }}">#{{ $r->peringkat }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="c nowrap"><span class="muted small">{{ $r->sakit }} / {{ $r->izin }} / {{ $r->alpa }}</span></td>
                </tr>
            @endforeach
            <tr style="background:#f2f7fe;font-weight:700">
                <td colspan="4" class="right" style="color:var(--navy)">Rata-rata Kelas</td>
                @foreach($subjects as $sub)
                    @php($st = $subjectStats[$sub->id] ?? null)
                    <td class="c" style="color:var(--navy)">{{ $st && $st['avg'] !== null ? number_format($st['avg'], 1, ',', '') : '-' }}</td>
                @endforeach
                <td class="c" style="color:var(--navy)">{{ $classTotals['avg_jumlah'] !== null ? number_format($classTotals['avg_jumlah'], 1, ',', '') : '-' }}</td>
                <td class="c" style="color:var(--navy)">{{ $classTotals['avg_rata'] !== null ? number_format($classTotals['avg_rata'], 1, ',', '') : '-' }}</td>
                <td colspan="2"></td>
            </tr>
            <tr style="background:#f8fbff;font-weight:600">
                <td colspan="4" class="right muted small">Nilai Tertinggi</td>
                @foreach($subjects as $sub)
                    @php($st = $subjectStats[$sub->id] ?? null)
                    <td class="c small">{{ $st && $st['max'] !== null ? $st['max'] : '-' }}</td>
                @endforeach
                <td class="c small">{{ $classTotals['max_jumlah'] ?? '-' }}</td>
                <td class="c small">{{ $classTotals['max_rata'] !== null ? number_format($classTotals['max_rata'], 1, ',', '') : '-' }}</td>
                <td colspan="2"></td>
            </tr>
            <tr style="background:#f8fbff;font-weight:600">
                <td colspan="4" class="right muted small">Nilai Terendah</td>
                @foreach($subjects as $sub)
                    @php($st = $subjectStats[$sub->id] ?? null)
                    <td class="c small">{{ $st && $st['min'] !== null ? $st['min'] : '-' }}</td>
                @endforeach
                <td class="c small">{{ $classTotals['min_jumlah'] ?? '-' }}</td>
                <td class="c small">{{ $classTotals['min_rata'] !== null ? number_format($classTotals['min_rata'], 1, ',', '') : '-' }}</td>
                <td colspan="2"></td>
            </tr>
            </tbody>
        </table></div>
        @if($subjects->isNotEmpty())
            <div class="card-b" style="padding:12px 18px;border-top:1px solid var(--line);background:var(--gray-50)">
                <div class="small muted"><b>Keterangan Kode Mata Pelajaran:</b>
                    @foreach($subjects as $sub)
                        <span style="display:inline-block;margin-right:12px"><b style="color:var(--navy)">{{ $sub->kode }}</b> = {{ $sub->nama }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <x-empty icon="users" text="Pilih kelas yang memiliki peserta didik untuk melihat dan mengunduh leger nilai akhir." />
    @endif
</div>
@endsection
