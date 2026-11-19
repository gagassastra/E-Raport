@extends('layouts.app')
@section('title', 'Input Nilai')
@section('content')
<div class="page-head">
    <div>
        <h1>Input Nilai</h1>
        <p>Isi nilai Sumatif, ASTS, dan ASAS untuk seluruh mata pelajaran kelas yang Anda ampu. Nilai Akhir dihitung otomatis setelah ketiga komponen terisi.</p>
    </div>
    @if($class && $semester && $subject && $rows->isNotEmpty())
        <div class="btn-group">
            <a class="btn green" href="{{ route('academic.scores.export', ['semester_id' => $semester->id, 'kelas_id' => $class->id, 'mapel_id' => $subject->id]) }}">@icon('download') Export Excel (.xlsx)</a>
        </div>
    @endif
</div>

<div class="card">
    <form class="filters" method="GET">
        <div class="field"><label>Semester</label><select class="input" name="semester_id" data-autosubmit>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semester && $semester->id === $s->id)>{{ $s->academicYear->nama }} — {{ ucfirst($s->jenis) }}{{ $s->is_active ? ' (aktif)' : '' }}</option>@endforeach</select></div>
        <div class="field"><label>Kelas</label><select class="input" name="kelas_id" data-autosubmit>@foreach($classes as $c)<option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
        <div class="field"><label>Mata Pelajaran</label><select class="input" name="mapel_id" data-autosubmit>@foreach($subjects as $m)<option value="{{ $m->id }}" @selected($subject && $subject->id === $m->id)>{{ $m->nama }}</option>@endforeach</select></div>
    </form>

    @if(!$class)
        <x-empty icon="class" text="Belum ada kelas yang ditugaskan kepada Anda pada tahun ajaran ini. Hubungi Operator." />
    @elseif($subjects->isEmpty())
        <x-empty icon="book" text="Belum ada mata pelajaran aktif untuk program, tingkat, dan kurikulum kelas ini. Hubungi Operator." />
    @elseif($rows->isEmpty())
        <x-empty icon="users" text="Belum ada peserta didik dalam kelas ini." />
    @else
    @unless($readonly)
    <div class="card-b" style="padding:12px 18px;border-bottom:1px solid var(--sky-200);background:#f7faff">
        <form method="POST" action="{{ route('academic.scores.import') }}" enctype="multipart/form-data" class="flex items-center between wrap gap">
            @csrf
            <input type="hidden" name="semester_id" value="{{ $semester->id }}">
            <input type="hidden" name="kelas_id" value="{{ $class->id }}">
            <input type="hidden" name="mapel_id" value="{{ $subject->id }}">
            <div>
                <div class="small" style="color:var(--navy);display:flex;align-items:center;gap:6px"><b>@icon('file-check') Pengisian Nilai via Microsoft Excel (.xlsx)</b></div>
                <div class="muted small">Unduh template Excel mata pelajaran ini, isi nilai di Excel, lalu unggah kembali berkasnya untuk mengisi nilai secara massal.</div>
            </div>
            <div class="flex gap items-center wrap">
                <a class="btn light sm" href="{{ route('academic.scores.export', ['semester_id' => $semester->id, 'kelas_id' => $class->id, 'mapel_id' => $subject->id]) }}">@icon('download') Unduh Template / Export Excel</a>
                <label class="check small" title="Jika kolom Deskripsi di Excel dikosongkan, sistem akan mengisi deskripsi rekomendasi otomatis sesuai Nilai Akhir">
                    <input type="checkbox" name="auto_deskripsi" value="1" checked>
                    <span>Auto-deskripsi jika kosong</span>
                </label>
                <input class="input" type="file" name="file_excel" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required style="max-width:240px;padding:5px 8px;font-size:12.5px">
                <button class="btn sky sm" type="submit">@icon('upload') Import Excel</button>
            </div>
        </form>
    </div>
    @endunless
    <form method="POST" action="{{ route('academic.scores.store') }}">@csrf
        <input type="hidden" name="semester_id" value="{{ $semester->id }}"><input type="hidden" name="kelas_id" value="{{ $class->id }}"><input type="hidden" name="mapel_id" value="{{ $subject->id }}">
        <div class="card-b" style="padding:10px 18px;border-bottom:1px solid var(--line)" >
            <span class="muted small">Rumus: @if($weight->mode === 'bobot')bobot Sumatif {{ $weight->bobot_sumatif }}% · ASTS {{ $weight->bobot_asts }}% · ASAS {{ $weight->bobot_asas }}% @else (Sumatif + ASTS + ASAS) ÷ 3 @endif, dibulatkan ke bilangan bulat. Kolom kosong tidak dihitung sebagai nol. Tekan Enter untuk pindah ke baris berikutnya.</span>
        </div>
        <div class="table-wrap">
        <table class="table score-table" data-score-table data-mode="{{ $weight->mode }}" data-ws="{{ $weight->bobot_sumatif }}" data-wa="{{ $weight->bobot_asts }}" data-wb="{{ $weight->bobot_asas }}" data-mapel="{{ $subject->nama }}">
            <thead>
                <tr><th>No</th><th>Nama Peserta Didik</th><th class="c">Sumatif</th><th class="c">ASTS</th><th class="c">ASAS</th><th class="c">Nilai Akhir</th><th>Status</th><th>Deskripsi Capaian Pembelajaran</th></tr>
                @unless($readonly)
                <tr class="bulk-score-row">
                    <th colspan="2">
                        <div class="bulk-title">@icon('layers') Input Otomatis Semua Siswa</div>
                        <div class="bulk-sub">Ketik nilai pada kolom di samping untuk menyamakan nilai seluruh siswa sekaligus.</div>
                    </th>
                    @foreach(['sumatif' => 'Sumatif', 'asts' => 'ASTS', 'asas' => 'ASAS'] as $f => $lbl)
                        <th class="c">
                            <div class="bulk-col">
                                <input class="sc bulk-sc" type="text" inputmode="decimal" data-bulk-input="{{ $f }}" placeholder="Semua" autocomplete="off" title="Ketik untuk mengisi nilai {{ $lbl }} ke semua siswa">
                                <button type="button" class="btn sky sm bulk-btn" data-noload="1" data-bulk-apply="{{ $f }}" title="Samakan nilai {{ $lbl }} seluruh siswa">Samakan</button>
                            </div>
                        </th>
                    @endforeach
                    <th class="c" colspan="2">
                        <div class="bulk-col">
                            <input class="sc bulk-sc" type="text" inputmode="decimal" data-bulk-input="all" placeholder="Semua" autocomplete="off" title="Ketik untuk mengisi Sumatif, ASTS, dan ASAS sekaligus ke semua siswa">
                            <button type="button" class="btn light sm bulk-btn" data-noload="1" data-bulk-apply="all" title="Samakan Sumatif, ASTS, dan ASAS untuk seluruh siswa">Isi 3 Nilai</button>
                        </div>
                    </th>
                    <th>
                        <div class="bulk-desc-wrap">
                            <input class="input bulk-desc-input" type="text" data-bulk-desc placeholder="Ketik deskripsi untuk semua siswa..." autocomplete="off">
                            <button type="button" class="btn light sm" data-noload="1" data-bulk-rec title="Isi deskripsi rekomendasi otomatis untuk semua siswa berdasarkan Nilai Akhir">Isi Rekomendasi Semua</button>
                        </div>
                    </th>
                </tr>
                @endunless
            </thead>
            <tbody>
            @foreach($rows as $i => $row)
                @php($dis = $row->locked || $readonly)
                <tr data-row class="{{ $row->locked ? 'row-locked' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td><b>{{ $row->student->nama }}</b><div class="muted small">{{ $row->student->nis }}@if($row->locked) · @icon('lock') terkunci @endif</div></td>
                    @foreach(['sumatif', 'asts', 'asas'] as $f)
                        <td class="c"><input class="sc" type="text" inputmode="decimal" data-field="{{ $f }}" name="scores[{{ $row->student->id }}][{{ $f }}]" value="{{ $row->score?->{$f} !== null ? rtrim(rtrim(number_format($row->score->{$f}, 2, '.', ''), '0'), '.') : '' }}" @disabled($dis) autocomplete="off"></td>
                    @endforeach
                    <td class="final">—</td>
                    <td><span class="badge stt">Kosong</span></td>
                    <td>
                        <textarea class="input desc" id="desc-{{ $row->student->id }}" name="scores[{{ $row->student->id }}][deskripsi]" @disabled($dis) placeholder="Deskripsi capaian pembelajaran">{{ $row->score?->deskripsi }}</textarea>
                        @if(!$dis)
                            <button type="button" class="btn light sm" style="margin-top:4px;{{ $row->score?->nilai_akhir === null ? 'display:none' : '' }}" data-noload="1" data-fill-desc="desc-{{ $row->student->id }}" data-text="{{ \App\Services\ScoreCalculator::rekomendasi($subject->nama, $row->score?->nilai_akhir) }}">Gunakan rekomendasi</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="card-b flex between items-center wrap gap">
            <span class="muted small">Rekomendasi deskripsi hanya bantuan berdasarkan nilai tersimpan; selalu sesuaikan dengan capaian peserta didik.</span>
            @unless($readonly)<button class="btn" type="submit">@icon('check') Simpan Nilai (Draft)</button>@endunless
        </div>
    </form>
    @endif
</div>
@endsection
