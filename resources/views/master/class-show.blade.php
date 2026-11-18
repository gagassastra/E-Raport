@extends('layouts.app')
@section('title', $class->nama)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $class->nama }}</h1>
        <p>{{ $class->program->nama }} ({{ $class->program->setara }}) · Kelas {{ $class->tingkat }} · Tahun Ajaran {{ $class->academicYear->nama }} · {{ $class->curriculum->nama }}</p>
    </div>
    <div class="btn-group">
        <a class="btn light" href="{{ route('master.classes.index', ['academic_year_id' => $class->academic_year_id]) }}">Kembali</a>
        <a class="btn sky" href="{{ route('reports.index', ['kelas_id' => $class->id]) }}">@icon('file-check') Lihat Rapor Kelas</a>
    </div>
</div>

@perm('classes.manage')
<div class="card mb">
    <div class="card-b" style="padding:14px 18px">
        <form method="POST" action="{{ route('master.classes.assign-teacher', $class) }}" class="flex items-center between wrap gap">
            @csrf @method('PUT')
            <div><b>Penetapan Guru / Wali Kelas:</b><div class="muted small">Guru/Wali Kelas bertanggung jawab penuh terhadap seluruh mata pelajaran, absensi, dan catatan pada kelas ini.</div></div>
            <div class="flex gap items-center">
                <select class="input" name="teacher_id" style="min-width:260px">
                    <option value="">— Belum ditetapkan —</option>
                    @foreach($teachers as $t)<option value="{{ $t->id }}" @selected($class->teacher_id == $t->id)>{{ $t->nama }}{{ $t->nip ? ' ('.$t->nip.')' : '' }}</option>@endforeach
                </select>
                <button class="btn" type="submit">@icon('check') Tetapkan Wali Kelas</button>
            </div>
        </form>
    </div>
</div>
@endperm

<div class="grid {{ auth()->user()->hasPermission('classes.manage') ? 'g-main' : '' }}" style="align-items:start">
    <div class="card">
        <div class="card-h"><h3>Daftar Peserta Didik ({{ $enrollments->count() }})</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>No</th><th>Nama Peserta Didik</th><th>NIS / NISN</th><th>JK</th><th>Status di Kelas</th><th></th></tr></thead>
            <tbody>
            @forelse($enrollments as $i => $e)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('master.students.show', $e->student) }}"><b>{{ $e->student->nama }}</b></a></td>
                    <td>{{ $e->student->nis }}<div class="muted small">{{ $e->student->nisn ?: '-' }}</div></td>
                    <td>{{ $e->student->jenis_kelamin }}</td>
                    <td><span class="badge {{ $e->status === 'aktif' ? 'green' : 'blue' }}">{{ \App\Models\StudentEnrollment::STATUS[$e->status] ?? $e->status }}</span></td>
                    <td class="actions">
                        @perm('classes.manage')
                            <form method="POST" action="{{ route('master.classes.remove-student', [$class, $e]) }}" style="display:inline" data-confirm="Keluarkan {{ $e->student->nama }} dari kelas {{ $class->nama }}?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash') Keluarkan</button></form>
                        @endperm
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="users" text="Belum ada peserta didik dalam kelas ini." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @perm('classes.manage')
    <div class="card">
        <div class="card-h"><h3>Tambah Peserta Didik ({{ $class->program->nama }})</h3></div>
        <div class="card-b">
            <p class="muted small" style="margin-top:0">Hanya menampilkan peserta didik aktif program <b>{{ $class->program->nama }}</b> yang belum memiliki kelas aktif pada Tahun Ajaran {{ $class->academicYear->nama }} (Paket B dan Paket C tidak dapat tercampur).</p>
            @if($candidates->isEmpty())
                <x-empty icon="users" text="Semua peserta didik aktif {{ $class->program->nama }} sudah ditempatkan di kelas pada tahun ajaran ini." />
            @else
                <form method="POST" action="{{ route('master.classes.enroll', $class) }}">
                    @csrf
                    <label class="check mb"><input type="checkbox" data-check-all=".cand-chk"> <b>Pilih semua ({{ $candidates->count() }})</b></label>
                    <div style="max-height:360px;overflow-y:auto;border:1px solid var(--line);border-radius:10px;padding:8px">
                        @foreach($candidates as $cand)
                            <label class="check" style="padding:6px 4px;border-bottom:1px solid var(--line)">
                                <input type="checkbox" class="cand-chk" name="student_ids[]" value="{{ $cand->id }}">
                                <span><b>{{ $cand->nama }}</b> <span class="muted small">({{ $cand->nis }})</span></span>
                            </label>
                        @endforeach
                    </div>
                    <button class="btn block mt" type="submit">@icon('plus') Masukkan ke Kelas Ini</button>
                </form>
            @endif
        </div>
    </div>
    @endperm
</div>
@endsection
