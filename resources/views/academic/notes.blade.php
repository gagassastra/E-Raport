@extends('layouts.app')
@section('title', 'Catatan Wali Kelas')
@section('content')
<div class="page-head"><div><h1>Catatan Wali Kelas</h1><p>Isi perkembangan, sikap, motivasi, saran, dan catatan untuk orang tua/wali setiap peserta didik.</p></div></div>
<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label><select class="input" name="semester_id" data-autosubmit>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}{{ $s->is_active ? ' (aktif)' : '' }}</option>@endforeach</select></div>
        <div class="field"><label>Kelas</label><select class="input" name="kelas_id" data-autosubmit>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
        <div class="field search"><label>Peserta didik</label><input class="input" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIS"></div>
        <button class="btn light" type="submit">@icon('search') Filter</button>
    </form>
    @if(!$class)<x-empty icon="class" text="Belum ada kelas yang dapat Anda akses pada tahun ajaran ini." />
    @elseif($rows->isEmpty())<x-empty icon="users" text="Tidak ada peserta didik yang cocok." />
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>No</th><th>Nama Peserta Didik</th><th>Ringkasan Catatan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($rows as $i => $row)
            <tr class="{{ $row->locked ? 'row-locked' : '' }}">
                <td>{{ $i + 1 }}</td><td><b>{{ $row->student->nama }}</b><div class="muted small">{{ $row->student->nis }}</div></td>
                <td class="small" style="max-width:420px">{{ \Illuminate\Support\Str::limit($row->note?->ringkasan() ?? '', 140) ?: '-' }}</td>
                <td>@if($row->note?->isFilled())<span class="badge green">Terisi</span>@else<span class="badge amber">Belum diisi</span>@endif @if($row->locked)<span class="badge dark">Terkunci</span>@endif</td>
                <td class="actions"><a class="btn light sm" href="{{ route('academic.notes.edit', [$row->student, 'kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">@icon('edit') {{ $row->locked ? 'Lihat' : 'Isi / Edit' }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
@endsection
