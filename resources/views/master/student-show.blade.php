@extends('layouts.app')
@section('title', 'Detail Peserta Didik')
@section('content')
<div class="page-head">
    <div><h1>{{ $student->nama }}</h1><p>NIS {{ $student->nis }} · NISN {{ $student->nisn ?: '-' }} · {{ $student->program->nama }} ({{ $student->program->setara }})</p></div>
    <div class="btn-group">
        <a class="btn light" href="{{ route('master.students.index') }}">Kembali</a>
        @perm('students.manage')<a class="btn" href="{{ route('master.students.edit', $student) }}">@icon('edit') Edit Data</a>@endperm
    </div>
</div>

<div class="grid g-main" style="align-items:start">
    <div class="grid">
        <div class="card">
            <div class="card-h"><h3>Riwayat Penempatan Kelas</h3></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Tahun Ajaran</th><th>Kelas</th><th>Tingkat</th><th>Status di Kelas</th><th>Catatan</th></tr></thead>
                <tbody>
                @forelse($student->enrollments as $e)
                    <tr>
                        <td><b>{{ $e->academicYear->nama }}</b></td>
                        <td>{{ $e->classroom->nama }}</td>
                        <td>Kelas {{ $e->classroom->tingkat }}</td>
                        <td><span class="badge {{ $e->status === 'aktif' ? 'green' : ($e->status === 'lulus' ? 'purple' : 'blue') }}">{{ \App\Models\StudentEnrollment::STATUS[$e->status] ?? $e->status }}</span></td>
                        <td class="small muted">{{ $e->catatan ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="class" text="Belum pernah ditempatkan di kelas." /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-h"><h3>Riwayat Rapor Semester</h3></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Tahun Ajaran</th><th>Semester</th><th>Kelas</th><th>Status Rapor</th><th></th></tr></thead>
                <tbody>
                @forelse($student->reportCards as $rc)
                    <tr>
                        <td><b>{{ $rc->semester->academicYear->nama }}</b></td>
                        <td>{{ ucfirst($rc->semester->jenis) }}</td>
                        <td>{{ $rc->classroom->nama }}</td>
                        <td><x-status-badge :status="$rc->status" /></td>
                        <td class="actions">
                            <a class="btn light sm" href="{{ route('reports.show', $rc) }}">@icon('eye') Lihat</a>
                            <a class="btn light sm" href="{{ route('reports.pdf', $rc) }}">@icon('download') PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="file" text="Belum ada riwayat rapor." /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h"><h3>Biodata & Akun</h3><span class="badge {{ $student->status === 'aktif' ? 'green' : 'purple' }}">{{ \App\Models\Student::STATUS[$student->status] ?? $student->status }}</span></div>
        <div class="card-b">
            <dl class="kv" style="grid-template-columns:130px 1fr">
                <dt>Nama</dt><dd>{{ $student->nama }}</dd>
                <dt>NIS</dt><dd>{{ $student->nis }}</dd>
                <dt>NISN</dt><dd>{{ $student->nisn ?: '-' }}</dd>
                <dt>Jenis Kelamin</dt><dd>{{ $student->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                <dt>TTL</dt><dd>{{ $student->tempat_lahir ?: '-' }}, {{ $student->tanggal_lahir?->translatedFormat('j F Y') ?? '-' }}</dd>
                <dt>Agama</dt><dd>{{ $student->agama ?: '-' }}</dd>
                <dt>Orang Tua/Wali</dt><dd>{{ $student->nama_wali ?: '-' }}</dd>
                <dt>Telepon</dt><dd>{{ $student->telepon ?: '-' }}</dd>
                <dt>Alamat</dt><dd>{{ $student->alamat ?: '-' }}</dd>
                <dt>Program</dt><dd>{{ $student->program->nama }}</dd>
                <dt>Tahun Masuk</dt><dd>{{ $student->tahun_masuk ?: '-' }}</dd>
                <dt>Akun Login</dt><dd><code>{{ $student->user?->username ?? '-' }}</code></dd>
            </dl>
        </div>
    </div>
</div>
@endsection
