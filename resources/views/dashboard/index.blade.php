@extends('layouts.app')
@section('title', 'Dasbor')
@section('content')
<div class="page-head">
    <div>
        <h1>Dasbor {{ $user->role?->nama }}</h1>
        <p>Sistem Informasi E-Raport {{ $school->nama_lembaga }} ·
            @if($activeYear) Tahun Ajaran <b>{{ $activeYear->nama }}</b> @else Belum ada Tahun Ajaran aktif @endif
            @if($activeSemester) · Semester <b>{{ ucfirst($activeSemester->jenis) }}</b> @endif
        </p>
    </div>
</div>

@if($roleSlug === 'siswa')
    {{-- DASHBOARD PESERTA DIDIK --}}
    <div class="grid g4 mb">
        <x-stat :value="$student?->program?->nama ?? '-'" label="Program Pendidikan" icon="layers" />
        <x-stat :value="$enrollment ? 'Kelas '.$enrollment->classroom->tingkat : '-'" label="Kelas Aktif" icon="class" />
        <x-stat :value="$activeYear?->nama ?? '-'" label="Tahun Ajaran Aktif" icon="calendar" />
        <x-stat :value="$activeSemester ? ucfirst($activeSemester->jenis) : '-'" label="Semester Aktif" icon="clock" tone="green" />
    </div>

    <div class="grid g-main" style="align-items:start">
        <div class="card">
            <div class="card-h"><h3>Daftar Rapor yang Tersedia (Disahkan)</h3></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Tahun Ajaran</th><th>Semester</th><th>Program & Kelas</th><th>Tanggal Pengesahan</th><th></th></tr></thead>
                <tbody>
                @forelse($reports as $rc)
                    <tr>
                        <td><b>{{ $rc->semester->academicYear->nama }}</b></td>
                        <td>{{ ucfirst($rc->semester->jenis) }}</td>
                        <td>{{ $rc->classroom->program->nama }} — Kelas {{ $rc->classroom->tingkat }}</td>
                        <td>{{ $rc->approved_at?->translatedFormat('j F Y') }}</td>
                        <td class="actions">
                            <a class="btn light sm" href="{{ route('reports.show', $rc) }}">@icon('eye') Lihat</a>
                            <a class="btn sky sm" href="{{ route('reports.pdf', $rc) }}">@icon('download') Unduh PDF</a>
                            <a class="btn light sm" target="_blank" href="{{ route('reports.print', $rc) }}">@icon('print') Cetak</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="file-check" text="Belum ada rapor semester yang disahkan." /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-h"><h3>Informasi Pribadi</h3></div>
            <div class="card-b">
                <dl class="kv" style="grid-template-columns:120px 1fr">
                    <dt>Nama</dt><dd>{{ $student?->nama ?? $user->name }}</dd>
                    <dt>NIS</dt><dd>{{ $student?->nis ?? '-' }}</dd>
                    <dt>NISN</dt><dd>{{ $student?->nisn ?: '-' }}</dd>
                    <dt>Program</dt><dd>{{ $student?->program?->nama ?? '-' }}</dd>
                    <dt>Rombel</dt><dd>{{ $enrollment?->classroom?->nama ?? '-' }}</dd>
                </dl>
                <a class="btn light block mt" href="{{ route('profile.show') }}">@icon('user') Lihat Profil Lengkap</a>
            </div>
        </div>
    </div>

@elseif($roleSlug === 'guru')
    {{-- DASHBOARD GURU / WALI KELAS --}}
    <div class="grid g4 mb">
        <x-stat :value="count($classes)" label="Kelas Tanggung Jawab" icon="class" />
        <x-stat :value="$totalSiswa" label="Jumlah Peserta Didik" icon="users" />
        <x-stat :value="$persenNilai.'%'" label="Progres Pengisian Nilai" icon="edit" tone="green" />
        <x-stat :value="$totalBelumLengkap" label="Nilai Belum Lengkap" icon="alert" tone="amber" />
    </div>
    <div class="grid g3 mb">
        <x-stat :value="$absenSummary['sakit'].'S / '.$absenSummary['izin'].'I / '.$absenSummary['alpa'].'A'" label="Rekap Absensi Kelas ({{ $absenSummary['terisi'] }} siswa diisi)" icon="clipboard" />
        <x-stat :value="$menungguPemeriksaan" label="Rapor Menunggu Pemeriksaan Wali Kelas" icon="file" tone="amber" />
        <x-stat :value="$sudahDikirim" label="Rapor Sudah Dikirim ke Kepala PKBM" icon="file-check" tone="green" />
    </div>

    <div class="card">
        <div class="card-h"><h3>Kelas yang Menjadi Tanggung Jawab Anda</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Nama Kelas</th><th class="c">Peserta Didik</th><th class="c">Mapel</th><th>Progres Nilai</th><th class="c">Belum Diajukan</th><th class="c">Terkirim / Disahkan</th><th>Aksi Cepat</th></tr></thead>
            <tbody>
            @forelse($guruSummaries as $g)
                <tr>
                    <td><b>{{ $g->class->nama }}</b><div class="muted small">{{ $g->class->program->nama }}</div></td>
                    <td class="c">{{ $g->progress['siswa'] }}</td>
                    <td class="c">{{ $g->progress['mapel'] }}</td>
                    <td style="min-width:160px"><div class="progress"><i style="width:{{ $g->progress['persen'] }}%"></i></div><span class="small muted">{{ $g->progress['lengkap'] }}/{{ $g->progress['diharapkan'] }} ({{ $g->progress['persen'] }}%)</span></td>
                    <td class="c"><span class="badge amber">{{ $g->perlu }}</span></td>
                    <td class="c"><span class="badge green">{{ $g->terkirim }} ({{ $g->disahkan }} sah)</span></td>
                    <td class="actions">
                        <a class="btn sky sm" href="{{ route('academic.scores.index', ['kelas_id' => $g->class->id, 'semester_id' => $activeSemester?->id]) }}">Input Nilai</a>
                        <a class="btn light sm" href="{{ route('academic.attendance.index', ['kelas_id' => $g->class->id, 'semester_id' => $activeSemester?->id]) }}">Absensi</a>
                        <a class="btn light sm" href="{{ route('academic.notes.index', ['kelas_id' => $g->class->id, 'semester_id' => $activeSemester?->id]) }}">Catatan</a>
                        <a class="btn light sm" href="{{ route('reports.index', ['kelas_id' => $g->class->id, 'semester_id' => $activeSemester?->id]) }}">Rapor</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="class" text="Anda belum ditugaskan sebagai Wali Kelas pada tahun ajaran aktif." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

@else
    {{-- DASHBOARD OPERATOR, KEPALA PKBM, DAN SUPER ADMIN --}}
    <div class="grid g4 mb">
        <x-stat :value="$totalSiswa" label="Total Peserta Didik Aktif" icon="users" />
        <x-stat :value="$totalGuru" label="Total Guru / Wali Kelas" icon="teacher" />
        @if($roleSlug === 'kepala_pkbm')
            <x-stat :value="$totalKelas" label="Total Kelas ({{ $totalKelasB }} Paket B · {{ $totalKelasC }} Paket C)" icon="class" />
            <x-stat :value="$raporMenunggu" label="Rapor Menunggu Pengesahan" icon="file" tone="amber" />
        @else
            <x-stat :value="$totalKelasB" label="Total Kelas Paket B" icon="class" />
            <x-stat :value="$totalKelasC" label="Total Kelas Paket C" icon="class" />
        @endif
    </div>

    <div class="grid g4 mb">
        <x-stat :value="($activeYear?->nama ?? '-').' · '.($activeSemester ? ucfirst($activeSemester->jenis) : '-')" label="Tahun Ajaran & Semester Aktif" icon="calendar" />
        <x-stat :value="$raporBelumSelesai" label="Rapor Belum Selesai" icon="edit" tone="amber" />
        <x-stat :value="$raporMenunggu" label="Rapor Menunggu Pengesahan" icon="clock" tone="purple" />
        <x-stat :value="$raporDisahkan" label="Rapor Sudah Disahkan" icon="check-circle" tone="green" />
    </div>

    @if($roleSlug === 'kepala_pkbm' && $pendingReports->isNotEmpty())
    <div class="card mb">
        <div class="card-h"><h3>Rapor Menunggu Pengesahan Kepala PKBM</h3><a class="btn light sm" href="{{ route('reports.index', ['status' => 'menunggu_pengesahan']) }}">Lihat Semua</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Peserta Didik</th><th>Kelas</th><th>Diajukan</th><th></th></tr></thead>
            <tbody>
            @foreach($pendingReports as $pr)
                <tr>
                    <td><b>{{ $pr->student->nama }}</b><div class="muted small">NIS {{ $pr->student->nis }}</div></td>
                    <td>{{ $pr->classroom->nama }}</td>
                    <td class="small muted">{{ $pr->submitted_at?->diffForHumans() }}</td>
                    <td class="actions"><a class="btn green sm" href="{{ route('reports.show', $pr) }}">@icon('file-check') Periksa & Sahkan</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    @endif

    <div class="grid g2 mb">
        <div class="card">
            <div class="card-h"><h3>Progres Pengisian & Perkembangan Nilai per Kelas</h3></div>
            <div class="card-b"><div class="chart-box"><canvas data-chart='@json($chartProgres)'></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-h"><h3>Komposisi Peserta Didik per Program</h3></div>
            <div class="card-b"><div class="chart-box"><canvas data-chart='@json($chartSiswa)'></canvas></div></div>
        </div>
    </div>

    <div class="card mb">
        <div class="card-h"><h3>Statistik Akademik Seluruh Kelas ({{ $activeSemester?->label ?? '-' }})</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Kelas</th><th>Program</th><th>Guru / Wali Kelas</th><th class="c">Peserta Didik</th><th class="c">Mapel</th><th>Progres Pengisian Nilai</th><th class="c">Rata-rata Nilai</th><th></th></tr></thead>
            <tbody>
            @forelse($classStats as $cs)
                <tr>
                    <td><b>{{ $cs->class->nama }}</b></td>
                    <td>{{ $cs->class->program->nama }}</td>
                    <td>{{ $cs->class->teacher?->nama ?? 'Belum ditetapkan' }}</td>
                    <td class="c">{{ $cs->progress['siswa'] }}</td>
                    <td class="c">{{ $cs->progress['mapel'] }}</td>
                    <td style="min-width:160px"><div class="progress"><i style="width:{{ $cs->progress['persen'] }}%"></i></div><span class="small muted">{{ $cs->progress['lengkap'] }}/{{ $cs->progress['diharapkan'] }} ({{ $cs->progress['persen'] }}%)</span></td>
                    <td class="c"><b>{{ $cs->rata_rata ?: '-' }}</b></td>
                    <td class="actions"><a class="btn light sm" href="{{ route('reports.index', ['kelas_id' => $cs->class->id, 'semester_id' => $activeSemester?->id]) }}">Rapor Kelas</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="class" text="Belum ada kelas pada tahun ajaran aktif." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @if($roleSlug === 'super_admin' && $recentLogs->isNotEmpty())
    <div class="card">
        <div class="card-h"><h3>Aktivitas Sistem Terbaru</h3><a class="btn light sm" href="{{ route('admin.logs.index') }}">Semua Log</a></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Deskripsi</th></tr></thead>
            <tbody>
            @foreach($recentLogs as $l)
                <tr>
                    <td class="small muted">{{ $l->created_at?->diffForHumans() }}</td>
                    <td><b>{{ $l->user_nama ?: 'Sistem' }}</b></td>
                    <td><span class="badge blue">{{ $l->modul }}</span></td>
                    <td>{{ $l->deskripsi }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    @endif
@endif
@endsection
