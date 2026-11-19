<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leger Nilai — {{ $class->nama }} ({{ $semester->label }})</title>
    @unless($isPdf ?? false)
        <link rel="icon" type="image/png" href="{{ route('school.logo') }}">
    @endunless
    <style>
        @page { size: A4 landscape; margin: 10mm 12mm; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.35; background: {{ ($isPdf ?? false) ? '#fff' : '#e9edf3' }}; }
        .toolbar { position: sticky; top: 0; z-index: 20; background: #0b2a5b; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .toolbar button, .toolbar a { background: #4a90d9; color: #fff; border: 0; padding: 7px 14px; border-radius: 8px; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar a.green { background: #15803d; }
        .toolbar a.light { background: #fff; color: #0b2a5b; }
        .sheet { background: #fff; {{ ($isPdf ?? false) ? '' : 'width: 297mm; max-width: 100%; min-height: 210mm; margin: 14px auto; padding: 10mm 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.15);' }} box-sizing: border-box; }
        table { border-collapse: collapse; width: 100%; }
        .kop { border-bottom: 3px double #0b2a5b; padding-bottom: 5px; margin-bottom: 8px; }
        .kop td { vertical-align: middle; }
        .inst { font-size: 15px; font-weight: bold; color: #0b2a5b; text-transform: uppercase; letter-spacing: .5px; }
        .sub { font-size: 9.5px; color: #333; }
        .title { text-align: center; font-size: 13px; font-weight: bold; color: #0b2a5b; text-transform: uppercase; letter-spacing: .8px; margin: 4px 0 8px; }
        .meta { margin-bottom: 8px; font-size: 10px; }
        .meta td { padding: 1.5px 4px; vertical-align: top; }
        .meta td.k { width: 130px; color: #333; }
        .meta td.s { width: 10px; text-align: center; }
        .meta td.v { font-weight: bold; color: #111; }
        table.leger th { background: #0b2a5b; color: #fff; border: 1px solid #0b2a5b; padding: 4px 4px; font-size: 9px; text-align: center; }
        table.leger th.sub-th { background: #163d7a; font-size: 8.5px; }
        table.leger td { border: 1px solid #444; padding: 3px 4px; font-size: 9.2px; vertical-align: middle; }
        table.leger tr.sum-row td { background: #f2f7fe; font-weight: bold; color: #0b2a5b; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        .legend { margin-top: 7px; font-size: 8.8px; color: #333; }
        .legend span { display: inline-block; margin-right: 12px; }
        .sign { margin-top: 14px; page-break-inside: avoid; font-size: 10px; }
        .sign td { width: 50%; text-align: center; vertical-align: top; }
        .ttd-space { height: 48px; }
        .nm { font-weight: bold; text-decoration: underline; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; }
        }
    </style>
</head>
<body @if(!($isPdf ?? false) && ($autoprint ?? false)) data-autoprint="1" @endif>
@unless($isPdf ?? false)
<div class="toolbar">
    <button onclick="window.print()">Cetak Leger</button>
    <a class="green" href="{{ route('academic.recap.excel', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">Unduh Excel (.xlsx)</a>
    <a href="{{ route('academic.recap.pdf', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">Unduh PDF</a>
    <a class="light" href="{{ route('academic.recap', ['semester_id' => $semester->id, 'kelas_id' => $class->id]) }}">Kembali</a>
    <span>Leger Nilai {{ $class->nama }} — {{ $semester->label }}</span>
</div>
@endunless

<div class="sheet">
    <div class="kop">
        <table><tr>
            <td style="width:68px">@if(!empty($logoDataUri))<img src="{{ $logoDataUri }}" style="width:58px;height:58px">@endif</td>
            <td style="text-align:center">
                <div class="inst">{{ $school->nama_lembaga }}</div>
                <div class="sub">{{ $school->jenis_lembaga }}@if($school->npsn) · NPSN: {{ $school->npsn }}@endif</div>
                @if($school->alamat_lengkap)<div class="sub">{{ $school->alamat_lengkap }}</div>@endif
            </td>
            <td style="width:68px"></td>
        </tr></table>
    </div>

    <div class="title">LEGER NILAI HASIL BELAJAR PESERTA DIDIK</div>

    <table class="meta">
        <tr>
            <td class="k">Program / Kelas</td><td class="s">:</td><td class="v">{{ $class->program->nama }} ({{ $class->program->setara }}) — {{ $class->nama }}</td>
            <td class="k">Tahun Ajaran</td><td class="s">:</td><td class="v">{{ $semester->academicYear->nama }}</td>
        </tr>
        <tr>
            <td class="k">Kurikulum</td><td class="s">:</td><td class="v">{{ $class->curriculum?->nama ?? '-' }}</td>
            <td class="k">Semester</td><td class="s">:</td><td class="v">{{ ucfirst($semester->jenis) }}</td>
        </tr>
        <tr>
            <td class="k">Guru / Wali Kelas</td><td class="s">:</td><td class="v">{{ $class->teacher?->nama ?? '-' }}</td>
            <td class="k">Jumlah Peserta Didik</td><td class="s">:</td><td class="v">{{ $rows->count() }} orang</td>
        </tr>
    </table>

    <table class="leger">
        <thead>
            <tr>
                <th rowspan="2" style="width:24px">No</th>
                <th rowspan="2" style="width:78px">NIS / NISN</th>
                <th rowspan="2">Nama Peserta Didik</th>
                <th rowspan="2" style="width:24px">L/P</th>
                @if($subjects->isNotEmpty())
                    <th colspan="{{ $subjects->count() }}">Mata Pelajaran (Nilai Akhir)</th>
                @endif
                <th rowspan="2" style="width:42px">Jumlah</th>
                <th rowspan="2" style="width:44px">Rata-rata</th>
                <th rowspan="2" style="width:42px">Peringkat</th>
                <th colspan="3">Absensi</th>
            </tr>
            <tr>
                @foreach($subjects as $sub)
                    <th class="sub-th" style="width:34px">{{ $sub->kode }}</th>
                @endforeach
                <th class="sub-th" style="width:22px">S</th>
                <th class="sub-th" style="width:22px">I</th>
                <th class="sub-th" style="width:22px">A</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $r->student->nis }}@if($r->student->nisn)<br><span style="font-size:8px;color:#555">{{ $r->student->nisn }}</span>@endif</td>
                    <td class="b">{{ $r->student->nama }}</td>
                    <td class="c">{{ $r->student->jenis_kelamin }}</td>
                    @foreach($subjects as $sub)
                        @php($val = $r->scores[$sub->id] ?? null)
                        <td class="c">{{ $val !== null ? $val : '-' }}</td>
                    @endforeach
                    <td class="c b">{{ $r->jumlah !== null ? $r->jumlah : '-' }}</td>
                    <td class="c b">{{ $r->rata_rata !== null ? number_format($r->rata_rata, 1, ',', '') : '-' }}</td>
                    <td class="c b">{{ $r->peringkat !== null ? $r->peringkat : '-' }}</td>
                    <td class="c">{{ $r->sakit }}</td>
                    <td class="c">{{ $r->izin }}</td>
                    <td class="c">{{ $r->alpa }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 10 + $subjects->count() }}" class="c">Belum ada data peserta didik pada kelas ini.</td></tr>
            @endforelse
            @if($rows->isNotEmpty())
                <tr class="sum-row">
                    <td colspan="4" style="text-align:right">Rata-rata Kelas</td>
                    @foreach($subjects as $sub)
                        @php($st = $subjectStats[$sub->id] ?? null)
                        <td class="c">{{ $st && $st['avg'] !== null ? number_format($st['avg'], 1, ',', '') : '-' }}</td>
                    @endforeach
                    <td class="c">{{ $classTotals['avg_jumlah'] !== null ? number_format($classTotals['avg_jumlah'], 1, ',', '') : '-' }}</td>
                    <td class="c">{{ $classTotals['avg_rata'] !== null ? number_format($classTotals['avg_rata'], 1, ',', '') : '-' }}</td>
                    <td colspan="4"></td>
                </tr>
                <tr class="sum-row">
                    <td colspan="4" style="text-align:right">Nilai Tertinggi</td>
                    @foreach($subjects as $sub)
                        @php($st = $subjectStats[$sub->id] ?? null)
                        <td class="c">{{ $st && $st['max'] !== null ? $st['max'] : '-' }}</td>
                    @endforeach
                    <td class="c">{{ $classTotals['max_jumlah'] ?? '-' }}</td>
                    <td class="c">{{ $classTotals['max_rata'] !== null ? number_format($classTotals['max_rata'], 1, ',', '') : '-' }}</td>
                    <td colspan="4"></td>
                </tr>
                <tr class="sum-row">
                    <td colspan="4" style="text-align:right">Nilai Terendah</td>
                    @foreach($subjects as $sub)
                        @php($st = $subjectStats[$sub->id] ?? null)
                        <td class="c">{{ $st && $st['min'] !== null ? $st['min'] : '-' }}</td>
                    @endforeach
                    <td class="c">{{ $classTotals['min_jumlah'] ?? '-' }}</td>
                    <td class="c">{{ $classTotals['min_rata'] !== null ? number_format($classTotals['min_rata'], 1, ',', '') : '-' }}</td>
                    <td colspan="4"></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if($subjects->isNotEmpty())
        <div class="legend">
            <b>Keterangan Kode Mata Pelajaran:</b>
            @foreach($subjects as $sub)
                <span><b>{{ $sub->kode }}</b> = {{ $sub->nama }}</span>
            @endforeach
        </div>
    @endif

    <table class="sign">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div>Kepala {{ $school->nama_lembaga }},</div>
                <div class="ttd-space"></div>
                <div class="nm">{{ $school->kepala_nama ?: '................................' }}</div>
            </td>
            <td>
                <div>{{ $titimangsa?->titimangsa ?? ($school->kabupaten ? $school->kabupaten.', '.now()->translatedFormat('j F Y') : now()->translatedFormat('j F Y')) }}</div>
                <div>Guru / Wali Kelas,</div>
                <div class="ttd-space"></div>
                <div class="nm">{{ $class->teacher?->nama ?: '................................' }}</div>
            </td>
        </tr>
    </table>
</div>

@if(!($isPdf ?? false))
<script>if (document.body.dataset.autoprint) setTimeout(function(){ window.print(); }, 600);</script>
@endif
</body>
</html>
