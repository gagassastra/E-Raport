@extends('layouts.app')
@section('title', 'Absensi')
@section('content')
<div class="page-head"><div><h1>Absensi Peserta Didik</h1><p>Rekap ketidakhadiran per semester (Sakit, Izin, Alpa). Data otomatis masuk ke rapor semester.</p></div></div>
<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label><select class="input" name="semester_id" data-autosubmit>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}{{ $s->is_active ? ' (aktif)' : '' }}</option>@endforeach</select></div>
        <div class="field"><label>Kelas</label><select class="input" name="kelas_id" data-autosubmit>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
        <div class="field search"><label>Peserta didik</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIS"></div>
        <button class="btn light" type="submit">@icon('search') Filter</button>
    </form>
    @if(!$class)
        <x-empty icon="class" text="Belum ada kelas yang dapat Anda akses pada tahun ajaran ini." />
    @elseif($rows->isEmpty())
        <x-empty icon="users" text="Tidak ada peserta didik yang cocok." />
    @else
    <form method="POST" action="{{ route('academic.attendance.store') }}">@csrf
        <input type="hidden" name="semester_id" value="{{ $semester->id }}"><input type="hidden" name="kelas_id" value="{{ $class->id }}">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>No</th><th>Nama Peserta Didik</th><th class="c">Sakit (hari)</th><th class="c">Izin (hari)</th><th class="c">Alpa (hari)</th><th class="c">Total</th></tr></thead>
            <tbody>
            @foreach($rows as $i => $row)
                @php($dis = $row->locked || $readonly)
                <tr class="{{ $row->locked ? 'row-locked' : '' }}">
                    <td>{{ $i + 1 }}</td><td><b>{{ $row->student->nama }}</b><div class="muted small">{{ $row->student->nis }}@if($row->locked) · terkunci @endif</div></td>
                    @foreach(['sakit', 'izin', 'alpa'] as $f)
                        <td class="c"><input class="input" style="width:84px;text-align:center;margin:auto" type="number" min="0" max="366" name="abs[{{ $row->student->id }}][{{ $f }}]" value="{{ $row->att?->{$f} ?? ($row->att ? 0 : '') }}" placeholder="0" @disabled($dis)></td>
                    @endforeach
                    <td class="c"><b>{{ $row->att ? $row->att->sakit + $row->att->izin + $row->att->alpa : '-' }}</b></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><th></th><th>Rekap Kelas</th><th class="c">{{ $total['sakit'] }}</th><th class="c">{{ $total['izin'] }}</th><th class="c">{{ $total['alpa'] }}</th><th class="c">{{ array_sum($total) }}</th></tr></tfoot>
        </table></div>
        @unless($readonly)<div class="card-b"><button class="btn" type="submit">@icon('check') Simpan Absensi</button> <span class="muted small">Kolom yang dikosongkan disimpan sebagai 0 hari.</span></div>@endunless
    </form>
    @endif
</div>
@endsection
