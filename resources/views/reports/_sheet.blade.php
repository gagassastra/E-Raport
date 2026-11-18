<style>
    .rp { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #111; line-height: 1.4; }
    .rp table { border-collapse: collapse; width: 100%; }
    .rp .rp-page { page-break-after: always; position: relative; box-sizing: border-box; }
    .rp .rp-page.rp-last { page-break-after: auto; }

    /* Kop Lembaga */
    .rp .rp-kop { border-bottom: 3px double #0b2a5b; padding-bottom: 5px; margin-bottom: 7px; }
    .rp .rp-head td { vertical-align: middle; }
    .rp .rp-inst { font-size: 15px; font-weight: bold; color: #0b2a5b; text-transform: uppercase; letter-spacing: .5px; }
    .rp .rp-sub { font-size: 9.5px; color: #333; }
    .rp .rp-draft { background: #fdf1d8; border: 1px dashed #b7791f; color: #8a5a00; text-align: center; font-weight: bold; padding: 3px 6px; margin-bottom: 6px; font-size: 9.5px; }

    /* Halaman 1: Cover / Sampul (Pas 1 Halaman A4: 210mm x 297mm) */
    .rp .cover-box { border: 4px double #0b2a5b; width: auto; height: 274mm; padding: 0 26px; text-align: center; box-sizing: content-box; position: relative; page-break-inside: avoid; }
    .rp .cover-box .rp-draft { position: absolute; top: 12px; left: 26px; right: 26px; margin-bottom: 0; }
    .rp .cover-logo { padding-top: 64px; margin: 0 auto 26px; }
    .rp .cover-logo img { width: 124px; height: 124px; object-fit: contain; }
    .rp .cover-logo-placeholder { width: 112px; height: 112px; border: 3px solid #0b2a5b; border-radius: 50%; margin: 0 auto; line-height: 112px; font-size: 28px; font-weight: bold; color: #0b2a5b; }
    .rp .cover-title { font-size: 24px; font-weight: bold; color: #0b2a5b; letter-spacing: 1.5px; margin-top: 14px; text-transform: uppercase; }
    .rp .cover-prog { font-size: 13.5px; font-weight: bold; color: #222; margin-top: 8px; text-transform: uppercase; letter-spacing: .6px; }
    .rp .cover-inst { font-size: 19px; font-weight: bold; color: #0b2a5b; margin-top: 38px; text-transform: uppercase; }
    .rp .cover-npsn { font-size: 11.5px; color: #444; margin-top: 5px; }
    .rp .cover-student-wrap { width: 78%; margin: 110px auto 0; text-align: center; }
    .rp .cover-label { font-size: 11px; font-weight: bold; letter-spacing: .8px; color: #333; margin-bottom: 6px; text-transform: uppercase; }
    .rp .cover-field { border: 1.5px solid #0b2a5b; border-radius: 6px; padding: 12px 16px; font-size: 14px; font-weight: bold; color: #0b2a5b; background: #f8fbff; margin-bottom: 26px; letter-spacing: .4px; }
    .rp .cover-footer { position: absolute; left: 26px; right: 26px; bottom: 42px; font-size: 12.5px; font-weight: bold; color: #111; line-height: 1.6; text-transform: uppercase; }

    /* Halaman 2 & 3: Biodata Sekolah & Biodata Siswa */
    .rp .bio-title { text-align: center; font-size: 14px; font-weight: bold; color: #0b2a5b; text-transform: uppercase; letter-spacing: .8px; margin: 10px 0 16px; text-decoration: underline; }
    .rp table.bio-table { width: 100%; margin-top: 6px; }
    .rp table.bio-table td { padding: 5px 6px; vertical-align: top; font-size: 11px; }
    .rp table.bio-table td.no { width: 26px; text-align: center; }
    .rp table.bio-table td.lbl { width: 210px; white-space: nowrap; color: #222; }
    .rp table.bio-table td.sep { width: 14px; text-align: center; }
    .rp table.bio-table td.val { font-weight: normal; color: #111; }
    .rp .foto-box { width: 30mm; height: 40mm; border: 1px solid #333; text-align: center; line-height: 40mm; font-size: 10px; color: #555; margin-left: auto; margin-right: 18px; }

    /* Halaman 4: Laporan Hasil Belajar (Nilai Akhir — Pas 1 Halaman A4) */
    .rp .rp-title { text-align: center; font-size: 12.5px; font-weight: bold; color: #0b2a5b; margin: 2px 0 6px; letter-spacing: .8px; text-transform: uppercase; }
    .rp table.rp-info { width: 100%; margin-bottom: 6px; border-bottom: 1.5px solid #0b2a5b; padding-bottom: 4px; }
    .rp table.rp-info td { padding: 1.5px 3px; vertical-align: top; font-size: 10px; line-height: 1.25; }
    .rp table.rp-info td.ik { width: 130px; white-space: nowrap; color: #222; }
    .rp table.rp-info td.is { width: 10px; text-align: center; white-space: nowrap; }
    .rp table.rp-info td.iv { width: 35%; font-weight: bold; color: #111; }
    .rp table.rp-info td.ik2 { width: 105px; white-space: nowrap; color: #222; padding-left: 12px; }
    .rp table.rp-info td.iv2 { font-weight: bold; color: #111; }

    .rp table.rp-nilai th { background: #0b2a5b; color: #fff; border: 1px solid #0b2a5b; padding: 4px 5px; font-size: 9.8px; text-align: center; }
    .rp table.rp-nilai td { border: 1px solid #444; padding: 3px 5px; vertical-align: top; font-size: 9.8px; line-height: 1.25; }
    .rp table.rp-nilai tr { page-break-inside: avoid; }
    .rp table.rp-nilai thead { display: table-header-group; }
    .rp .c { text-align: center; } .rp .b { font-weight: bold; } .rp .desc { font-size: 9.2px; color: #222; line-height: 1.22; }
    .rp .rp-box { border: 1px solid #444; padding: 4px 7px; margin-top: 6px; page-break-inside: avoid; font-size: 9.8px; line-height: 1.25; }
    .rp .rp-box .t { font-weight: bold; margin-bottom: 2px; color: #0b2a5b; }
    .rp table.rp-abs td, .rp table.rp-abs th { border: 1px solid #444; padding: 2.5px 5px; text-align: center; font-size: 9.8px; }
    .rp table.rp-abs th { background: #e8f1fc; color: #0b2a5b; }
    .rp .rp-sign { margin-top: 8px; page-break-inside: avoid; font-size: 10px; line-height: 1.25; }
    .rp .rp-sign td { vertical-align: top; text-align: center; width: 33%; }
    .rp .ttd { height: 58px; padding: 2px 0; }
    .rp .ttd img { width: 54px; height: 54px; }
    .rp .rp-foot { font-size: 8px; color: #444; margin-top: 5px; text-align: center; }
    .rp .nm { font-weight: bold; text-decoration: underline; }
</style>
<?php /** @var array $r */ ?>
<div class="rp">

    {{-- ================= HALAMAN 1: SAMPUL / COVER RAPOR ================= --}}
    <div class="rp-page">
        <div class="cover-box">
            @if(empty($r['final']))
                <div class="rp-draft">PRATINJAU — RAPOR BELUM DISAHKAN (Status: {{ config('erapor.status_rapor.'.$r['status'].'.0', $r['status']) }})</div>
            @endif
            <div class="cover-logo">
                @if(!empty($r['lembaga']['logo']))
                    <img src="{{ $r['lembaga']['logo'] }}" alt="Logo">
                @else
                    <div class="cover-logo-placeholder">PKBM</div>
                @endif
            </div>

            <div class="cover-title">RAPOR PESERTA DIDIK</div>
            <div class="cover-prog">PENDIDIKAN KESETARAAN PROGRAM {{ strtoupper($r['kelas']['program']) }} ({{ strtoupper($r['kelas']['setara']) }})</div>

            <div class="cover-inst">{{ $r['lembaga']['nama'] }}</div>
            @if(!empty($r['lembaga']['npsn']))
                <div class="cover-npsn">NPSN: {{ $r['lembaga']['npsn'] }}</div>
            @endif

            <div class="cover-student-wrap">
                <div class="cover-label">NAMA PESERTA DIDIK</div>
                <div class="cover-field">{{ $r['siswa']['nama'] }}</div>

                <div class="cover-label">NOMOR INDUK SISWA (NIS) / NISN</div>
                <div class="cover-field">{{ $r['siswa']['nis'] }} / {{ $r['siswa']['nisn'] ?: '-' }}</div>
            </div>

            <div class="cover-footer">
                @if(!empty($r['lembaga']['kabupaten']) || !empty($r['lembaga']['provinsi']))
                    <div>{{ strtoupper(trim(($r['lembaga']['kabupaten'] ?? '').' — '.($r['lembaga']['provinsi'] ?? ''), ' —')) }}</div>
                @endif
                <div>TAHUN AJARAN {{ $r['kelas']['tahun_ajaran'] }}</div>
            </div>
        </div>
    </div>

    {{-- ================= HALAMAN 2: BIODATA SEKOLAH / LEMBAGA ================= --}}
    <div class="rp-page">
        <div class="rp-kop">
            <table class="rp-head"><tr>
                <td style="width:72px">@if(!empty($r['lembaga']['logo']))<img src="{{ $r['lembaga']['logo'] }}" style="width:62px;height:62px">@endif</td>
                <td style="text-align:center">
                    <div class="rp-inst">{{ $r['lembaga']['nama'] }}</div>
                    <div class="rp-sub">{{ $r['lembaga']['jenis'] }}@if($r['lembaga']['npsn']) · NPSN: {{ $r['lembaga']['npsn'] }}@endif</div>
                    @if($r['lembaga']['alamat'])<div class="rp-sub">{{ $r['lembaga']['alamat'] }}</div>@endif
                    @php($kontak = array_filter([$r['lembaga']['telepon'] ? 'Telp. '.$r['lembaga']['telepon'] : null, $r['lembaga']['email'], $r['lembaga']['website']]))
                    @if($kontak)<div class="rp-sub">{{ implode(' · ', $kontak) }}</div>@endif
                </td>
                <td style="width:72px"></td>
            </tr></table>
        </div>

        <div class="bio-title">IDENTITAS SATUAN PENDIDIKAN (LEMBAGA)</div>

        <table class="bio-table">
            <tr><td class="no">1.</td><td class="lbl">Nama Satuan Pendidikan</td><td class="sep">:</td><td class="val b">{{ $r['lembaga']['nama'] }}</td></tr>
            <tr><td class="no">2.</td><td class="lbl">Jenis Pendidikan</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['jenis'] ?: 'Pendidikan Nonformal' }}</td></tr>
            <tr><td class="no">3.</td><td class="lbl">Nomor Pokok Sekolah Nasional (NPSN)</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['npsn'] ?: '-' }}</td></tr>
            <tr><td class="no">4.</td><td class="lbl">Program Pendidikan</td><td class="sep">:</td><td class="val">{{ $r['kelas']['program'] }} ({{ $r['kelas']['setara'] }})</td></tr>
            <tr><td class="no">5.</td><td class="lbl">Kurikulum</td><td class="sep">:</td><td class="val">{{ $r['kelas']['kurikulum'] }}</td></tr>
            <tr><td class="no">6.</td><td class="lbl">Alamat Jalan / Kampung</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['alamat_jalan'] ?? ($r['lembaga']['alamat'] ?: '-') }}</td></tr>
            <tr><td class="no">7.</td><td class="lbl">Desa / Kelurahan</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['desa'] ?? '-' }}</td></tr>
            <tr><td class="no">8.</td><td class="lbl">Kecamatan</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['kecamatan'] ?? '-' }}</td></tr>
            <tr><td class="no">9.</td><td class="lbl">Kabupaten / Kota</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['kabupaten'] ?? '-' }}</td></tr>
            <tr><td class="no">10.</td><td class="lbl">Provinsi</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['provinsi'] ?? '-' }}</td></tr>
            <tr><td class="no">11.</td><td class="lbl">Kode Pos</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['kode_pos'] ?? '-' }}</td></tr>
            <tr><td class="no">12.</td><td class="lbl">Nomor Telepon</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['telepon'] ?: '-' }}</td></tr>
            <tr><td class="no">13.</td><td class="lbl">Alamat Email</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['email'] ?: '-' }}</td></tr>
            <tr><td class="no">14.</td><td class="lbl">Website</td><td class="sep">:</td><td class="val">{{ $r['lembaga']['website'] ?: '-' }}</td></tr>
            <tr><td class="no">15.</td><td class="lbl">Kepala {{ $r['lembaga']['nama'] }}</td><td class="sep">:</td><td class="val b">{{ $r['kepala']['nama'] }}</td></tr>
        </table>
    </div>

    {{-- ================= HALAMAN 3: BIODATA PESERTA DIDIK ================= --}}
    <div class="rp-page">
        <div class="rp-kop">
            <table class="rp-head"><tr>
                <td style="width:72px">@if(!empty($r['lembaga']['logo']))<img src="{{ $r['lembaga']['logo'] }}" style="width:62px;height:62px">@endif</td>
                <td style="text-align:center">
                    <div class="rp-inst">{{ $r['lembaga']['nama'] }}</div>
                    <div class="rp-sub">{{ $r['lembaga']['jenis'] }}@if($r['lembaga']['npsn']) · NPSN: {{ $r['lembaga']['npsn'] }}@endif</div>
                    @if($r['lembaga']['alamat'])<div class="rp-sub">{{ $r['lembaga']['alamat'] }}</div>@endif
                </td>
                <td style="width:72px"></td>
            </tr></table>
        </div>

        <div class="bio-title">KETERANGAN TENTANG DIRI PESERTA DIDIK</div>

        <table class="bio-table">
            <tr><td class="no">1.</td><td class="lbl">Nama Lengkap Peserta Didik</td><td class="sep">:</td><td class="val b">{{ $r['siswa']['nama'] }}</td></tr>
            <tr><td class="no">2.</td><td class="lbl">Nomor Induk Siswa (NIS)</td><td class="sep">:</td><td class="val">{{ $r['siswa']['nis'] }}</td></tr>
            <tr><td class="no">3.</td><td class="lbl">Nomor Induk Siswa Nasional (NISN)</td><td class="sep">:</td><td class="val">{{ $r['siswa']['nisn'] ?: '-' }}</td></tr>
            <tr><td class="no">4.</td><td class="lbl">Tempat, Tanggal Lahir</td><td class="sep">:</td><td class="val">{{ $r['siswa']['ttl'] ?: '-' }}</td></tr>
            <tr><td class="no">5.</td><td class="lbl">Jenis Kelamin</td><td class="sep">:</td><td class="val">{{ $r['siswa']['jk_label'] ?? (($r['siswa']['jk'] ?? '') === 'L' ? 'Laki-laki' : 'Perempuan') }}</td></tr>
            <tr><td class="no">6.</td><td class="lbl">Agama</td><td class="sep">:</td><td class="val">{{ $r['siswa']['agama'] ?: '-' }}</td></tr>
            <tr><td class="no">7.</td><td class="lbl">Alamat Peserta Didik</td><td class="sep">:</td><td class="val">{{ $r['siswa']['alamat'] ?: '-' }}</td></tr>
            <tr><td class="no">8.</td><td class="lbl">Nomor Telepon / HP</td><td class="sep">:</td><td class="val">{{ $r['siswa']['telepon'] ?? '-' }}</td></tr>
            <tr><td class="no">9.</td><td class="lbl">Diterima di Lembaga ini</td><td class="sep"></td><td class="val"></td></tr>
            <tr><td class="no"></td><td class="lbl">a. Program Pendidikan</td><td class="sep">:</td><td class="val">{{ $r['kelas']['program'] }} ({{ $r['kelas']['setara'] }})</td></tr>
            <tr><td class="no"></td><td class="lbl">b. Tingkat / Kelas</td><td class="sep">:</td><td class="val">Kelas {{ $r['kelas']['tingkat'] }}</td></tr>
            <tr><td class="no"></td><td class="lbl">c. Tahun Masuk</td><td class="sep">:</td><td class="val">{{ $r['siswa']['tahun_masuk'] ?? '-' }}</td></tr>
            <tr><td class="no">10.</td><td class="lbl">Nama Orang Tua / Wali</td><td class="sep">:</td><td class="val">{{ $r['siswa']['nama_wali'] ?: '-' }}</td></tr>
            <tr><td class="no">11.</td><td class="lbl">Status Peserta Didik</td><td class="sep">:</td><td class="val">{{ $r['siswa']['status'] ?? 'Aktif' }}</td></tr>
        </table>

        <table style="width:100%;margin-top:32px">
            <tr>
                <td style="width:55%;vertical-align:top;text-align:right;padding-right:24px">
                    <div class="foto-box">Pas Foto 3 x 4</div>
                </td>
                <td style="width:45%;vertical-align:top;text-align:center">
                    <div>{{ $r['titimangsa']['teks'] ?? '[Titimangsa belum diatur]' }}</div>
                    <div>{{ $r['kepala']['jabatan'] }},</div>
                    <div class="ttd">
                        @if(!empty($r['kepala']['qr_ttd']))
                            <img src="{{ $r['kepala']['qr_ttd'] }}" alt="QR TTD Kepala PKBM">
                        @endif
                    </div>
                    <div class="nm">{{ $r['kepala']['nama'] }}</div>
                    @if(!empty($r['kepala']['identitas']))<div>{{ $r['kepala']['identitas_label'] }}: {{ $r['kepala']['identitas'] }}</div>@endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= HALAMAN 4: LAPORAN HASIL BELAJAR (NILAI AKHIR) ================= --}}
    <div class="rp-page rp-last">
        @if(empty($r['final']))
            <div class="rp-draft">PRATINJAU — RAPOR BELUM DISAHKAN (Status: {{ config('erapor.status_rapor.'.$r['status'].'.0', $r['status']) }})</div>
        @endif

        <div class="rp-kop">
            <table class="rp-head"><tr>
                <td style="width:72px">@if(!empty($r['lembaga']['logo']))<img src="{{ $r['lembaga']['logo'] }}" style="width:62px;height:62px">@endif</td>
                <td style="text-align:center">
                    <div class="rp-inst">{{ $r['lembaga']['nama'] }}</div>
                    <div class="rp-sub">{{ $r['lembaga']['jenis'] }}@if($r['lembaga']['npsn']) · NPSN: {{ $r['lembaga']['npsn'] }}@endif</div>
                    @if($r['lembaga']['alamat'])<div class="rp-sub">{{ $r['lembaga']['alamat'] }}</div>@endif
                    @php($kontak = array_filter([$r['lembaga']['telepon'] ? 'Telp. '.$r['lembaga']['telepon'] : null, $r['lembaga']['email'], $r['lembaga']['website']]))
                    @if($kontak)<div class="rp-sub">{{ implode(' · ', $kontak) }}</div>@endif
                </td>
                <td style="width:72px"></td>
            </tr></table>
        </div>

        <div class="rp-title">LAPORAN HASIL BELAJAR (RAPOR)</div>

        {{-- Identitas Peserta Didik Memanjang ke Pinggir (Tidak Turun ke Bawah) --}}
        <table class="rp-info">
            <tr>
                <td class="ik">Nama Peserta Didik</td>
                <td class="is">:</td>
                <td class="iv">{{ $r['siswa']['nama'] }}</td>
                <td class="ik2">Program / Kelas</td>
                <td class="is">:</td>
                <td class="iv2">{{ $r['kelas']['program'] }} — Kelas {{ $r['kelas']['tingkat'] }}</td>
            </tr>
            <tr>
                <td class="ik">NIS / NISN</td>
                <td class="is">:</td>
                <td class="iv">{{ $r['siswa']['nis'] }} / {{ $r['siswa']['nisn'] ?: '-' }}</td>
                <td class="ik2">Semester</td>
                <td class="is">:</td>
                <td class="iv2">{{ $r['kelas']['semester'] }}</td>
            </tr>
            <tr>
                <td class="ik">Nama Satuan Pendidikan</td>
                <td class="is">:</td>
                <td class="iv">{{ $r['lembaga']['nama'] }}</td>
                <td class="ik2">Tahun Ajaran</td>
                <td class="is">:</td>
                <td class="iv2">{{ $r['kelas']['tahun_ajaran'] }}</td>
            </tr>
        </table>

        {{-- Tabel Nilai: Hanya Menampilkan Nilai Akhir & Capaian Kompetensi --}}
        <table class="rp-nilai">
            <thead>
                <tr>
                    <th style="width:6%">No</th>
                    <th style="width:34%">Mata Pelajaran</th>
                    <th style="width:12%">Nilai Akhir</th>
                    <th style="width:48%">Capaian Kompetensi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($r['nilai'] as $n)
                <tr>
                    <td class="c">{{ $n['no'] }}</td>
                    <td>{{ $n['nama'] }}</td>
                    <td class="c b">{{ $n['akhir'] ?? '-' }}</td>
                    <td class="desc">{{ $n['deskripsi'] ?: '-' }}</td>
                </tr>
            @endforeach
            @if(count($r['nilai']) === 0)
                <tr><td colspan="4" class="c">Belum ada mata pelajaran.</td></tr>
            @endif
            @if($r['rata_rata'] !== null)
                <tr>
                    <td colspan="2" class="b" style="text-align:right">Rata-rata Nilai Akhir</td>
                    <td class="c b">{{ number_format($r['rata_rata'], 1, ',', '') }}</td>
                    <td></td>
                </tr>
            @endif
            </tbody>
        </table>

        <table style="margin-top:10px"><tr>
            <td style="width:36%;vertical-align:top">
                <table class="rp-abs">
                    <tr><th colspan="3">Ketidakhadiran</th></tr>
                    <tr><th>Sakit</th><th>Izin</th><th>Alpa</th></tr>
                    <tr><td>{{ $r['absensi']['sakit'] }} hari</td><td>{{ $r['absensi']['izin'] }} hari</td><td>{{ $r['absensi']['alpa'] }} hari</td></tr>
                </table>
            </td>
            <td style="width:2%"></td>
            <td style="vertical-align:top">
                <div class="rp-box" style="margin-top:0">
                    <div class="t">Catatan Guru/Wali Kelas</div>
                    @forelse($r['catatan'] as $c)
                        <div><b>{{ $c['label'] }}:</b> {{ $c['isi'] }}</div>
                    @empty
                        <div>-</div>
                    @endforelse
                </div>
            </td>
        </tr></table>

        <table class="rp-sign"><tr>
            <td>
                <div>&nbsp;</div>
                <div>Orang Tua/Wali Peserta Didik,</div>
                <div class="ttd"></div>
                <div>.............................................</div>
            </td>
            <td>
                <div>Mengetahui,</div>
                <div>{{ $r['kepala']['jabatan'] }},</div>
                <div class="ttd">
                    @if(!empty($r['kepala']['qr_ttd']))
                        <img src="{{ $r['kepala']['qr_ttd'] }}" alt="QR TTD Kepala PKBM">
                    @endif
                </div>
                <div class="nm">{{ $r['kepala']['nama'] }}</div>
                @if(!empty($r['kepala']['identitas']))<div>{{ $r['kepala']['identitas_label'] }}: {{ $r['kepala']['identitas'] }}</div>@endif
            </td>
            <td>
                <div>{{ $r['titimangsa']['teks'] ?? '[Titimangsa belum diatur]' }}</div>
                <div>Guru/Wali Kelas,</div>
                <div class="ttd">
                    @if(!empty($r['wali']['qr_ttd']))
                        <img src="{{ $r['wali']['qr_ttd'] }}" alt="QR TTD Wali Kelas">
                    @endif
                </div>
                <div class="nm">{{ $r['wali']['nama'] }}</div>
                @if(!empty($r['wali']['identitas']))<div>{{ $r['wali']['identitas_label'] }}: {{ $r['wali']['identitas'] }}</div>@endif
            </td>
        </tr></table>

        @if(!empty($r['final']))
            <div class="rp-foot">Dokumen ini ditandatangani dan disahkan secara elektronik menggunakan QR Code pada {{ \Carbon\Carbon::parse($r['kepala']['ditandatangani_at'])->timezone(config('app.timezone'))->translatedFormat('j F Y H:i') }} WIB (versi {{ $r['versi'] }}). Pindai QR Code tanda tangan atau kunjungi {{ $r['verify_url'] }} untuk verifikasi keaslian rapor.</div>
        @endif
    </div>

</div>
