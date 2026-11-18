@extends('layouts.app')
@section('title', 'Pratinjau Rapor')
@section('content')
@php($sheetStyleCard = true)
<div class="page-head">
    <div>
        <h1>{{ $card->student->nama }}</h1>
        <p>{{ $card->classroom->nama }} · Semester {{ ucfirst($card->semester->jenis) }} {{ $card->semester->academicYear->nama }}</p>
    </div>
    <div class="btn-group">
        @unless($isSiswa)<a class="btn light" href="{{ route('reports.index', ['kelas_id' => $card->class_id, 'semester_id' => $card->semester_id]) }}">Kembali</a>@else<a class="btn light" href="{{ route('portal.reports') }}">Kembali</a>@endunless
        <a class="btn sky" href="{{ route('reports.pdf', $card) }}">@icon('download') Unduh PDF</a>
        <a class="btn light" target="_blank" href="{{ route('reports.print', $card) }}">@icon('print') Cetak</a>
    </div>
</div>

<div class="grid g-main" style="align-items:start">
    <div style="overflow-x:auto">
        <div class="report-sheet">@include('reports._sheet', ['r' => $r])</div>
    </div>

    <div class="grid">
        <div class="card"><div class="card-b">
            <div class="flex between items-center mb"><h3>Status Rapor</h3><x-status-badge :status="$card->status" /></div>
            @if($card->catatan_perbaikan && in_array($card->status, ['ditolak', 'dibuka_kembali', 'dibatalkan']))
                <div class="alert warning">@icon('alert')<div><b>Catatan:</b> {{ $card->catatan_perbaikan }}</div></div>
            @endif
            @if($kelengkapan)
                @if($kelengkapan['ok'])<div class="alert success">@icon('check-circle')<div>Data rapor sudah lengkap.</div></div>
                @else<div class="alert warning">@icon('alert')<div><b>Belum lengkap:</b><ul style="margin:4px 0 0;padding-left:18px">@foreach($kelengkapan['missing'] as $m)<li>{{ $m }}</li>@endforeach</ul></div></div>@endif
            @endif

            @if($can['submit'])
                <form method="POST" action="{{ route('reports.submit', $card) }}" data-confirm="Dengan menekan tombol ini Anda menandatangani rapor secara digital dan mengirimkannya kepada Kepala PKBM. Data nilai, absensi, dan catatan akan terkunci." data-confirm-title="Tanda Tangan & Ajukan Rapor" data-confirm-class="sky" data-confirm-ok="Tanda tangani & ajukan">@csrf
                    <button class="btn sky block" type="submit" @disabled($kelengkapan && !$kelengkapan['ok'])>@icon('pen') Tanda Tangani & Ajukan</button></form>
            @endif
            @if($can['edit_data'])
                <div class="btn-group mt"><a class="btn light sm" href="{{ route('academic.scores.index', ['kelas_id' => $card->class_id, 'semester_id' => $card->semester_id]) }}">Nilai</a><a class="btn light sm" href="{{ route('academic.attendance.index', ['kelas_id' => $card->class_id, 'semester_id' => $card->semester_id]) }}">Absensi</a><a class="btn light sm" href="{{ route('academic.notes.index', ['kelas_id' => $card->class_id, 'semester_id' => $card->semester_id]) }}">Catatan</a></div>
            @endif
            @if($can['approve'])
                <div class="btn-group">
                    <form method="POST" action="{{ route('reports.approve', $card) }}" data-confirm="Anda akan mengesahkan rapor ini dengan tanda tangan digital Anda. Rapor akan diterbitkan (QR Code & PDF resmi dibuat) dan dikunci." data-confirm-title="Sahkan Rapor" data-confirm-class="green" data-confirm-ok="Ya, sahkan">@csrf<button class="btn green" type="submit">@icon('check-circle') Setujui & Sahkan</button></form>
                    <form method="POST" action="{{ route('reports.reject', $card) }}" data-confirm="Rapor akan dikembalikan kepada Guru/Wali Kelas untuk diperbaiki." data-confirm-title="Minta Perbaikan" data-confirm-ok="Kembalikan" data-prompt="Catatan perbaikan untuk Guru/Wali Kelas" data-confirm-class="amber">@csrf<button class="btn amber" type="submit">@icon('x-circle') Minta Perbaikan</button></form>
                </div>
            @endif
            @if($can['reopen'])
                <form method="POST" action="{{ route('reports.reopen', $card) }}" class="mt" data-confirm="Rapor akan dibuka kembali. Versi yang sudah terbit dinyatakan TIDAK BERLAKU, QR Code lama menjadi tidak valid, dan proses pengesahan harus diulang. Tindakan ini dicatat dalam log." data-confirm-title="Buka Kembali Rapor" data-confirm-ok="Buka kembali" data-prompt="Alasan membuka kembali rapor" data-confirm-class="purple">@csrf<button class="btn purple block" type="submit">@icon('unlock') Buka Kembali Rapor</button></form>
            @endif
            @if($can['cancel'])
                <form method="POST" action="{{ route('reports.cancel', $card) }}" class="mt" data-confirm="Rapor akan dibatalkan dan versi yang terbit (jika ada) dinyatakan tidak berlaku." data-confirm-title="Batalkan Rapor" data-confirm-ok="Batalkan rapor" data-prompt="Alasan pembatalan">@csrf<button class="btn red block" type="submit">@icon('x-circle') Batalkan Rapor</button></form>
            @endif
        </div></div>

        @if($can['titimangsa'])
        <div class="card"><div class="card-b">
            <h3 class="mb">Titimangsa Rapor</h3>
            <form method="POST" action="{{ route('reports.settings.update', $card->semester) }}">@csrf @method('PUT')
                <div class="grid">
                    <x-field name="tempat" label="Tempat penerbitan" :value="$titimangsa?->tempat" required placeholder="mis. nama kabupaten/kota" />
                    <x-field name="tanggal_terbit" type="date" label="Tanggal penerbitan" :value="$titimangsa?->tanggal_terbit?->toDateString()" required />
                </div>
                <p class="muted small">Berlaku untuk seluruh rapor semester ini yang belum disahkan, dan langsung tampil pada pratinjau.</p>
                <button class="btn sm" type="submit">Simpan Titimangsa</button>
            </form>
        </div></div>
        @endif

        @if($verification)
        <div class="card"><div class="card-b">
            <h3 class="mb">Verifikasi & Integritas</h3>
            <dl class="kv" style="grid-template-columns:100px 1fr">
                <dt>Versi</dt><dd>{{ $card->versi_aktif }}</dd>
                <dt>Disahkan</dt><dd>{{ $card->approved_at?->translatedFormat('j F Y H:i') }}</dd>
                <dt>Hash PDF</dt><dd class="small" style="word-break:break-all">{{ $card->activeVersion()?->pdf_hash }}</dd>
            </dl>
            <a class="btn light sm mt" target="_blank" href="{{ route('verification.show', $verification->token) }}">@icon('qr') Buka Halaman Verifikasi</a>
        </div></div>
        @endif

        @unless($isSiswa)
        <div class="card"><div class="card-b">
            <h3 class="mb">Riwayat Proses</h3>
            @forelse($card->approvals as $a)
                <div style="padding:8px 0;border-bottom:1px solid var(--line)">
                    <b>{{ \App\Models\ReportApproval::AKSI[$a->aksi] ?? $a->aksi }}</b>@if($a->versi) <span class="muted small">(v{{ $a->versi }})</span>@endif
                    <div class="small muted">{{ $a->nama_penanda }} · {{ $a->jabatan }} · {{ $a->waktu->translatedFormat('j M Y H:i') }}</div>
                    @if($a->catatan)<div class="small">“{{ $a->catatan }}”</div>@endif
                </div>
            @empty<div class="muted small">Belum ada riwayat proses.</div>@endforelse
            @if($card->versions->count())
                <h4 style="margin:14px 0 6px">Versi Rapor</h4>
                @foreach($card->versions as $v)
                    <div class="flex between items-center small" style="padding:4px 0"><span>Versi {{ $v->versi }} — {{ $v->diterbitkan_at?->translatedFormat('j M Y') }}</span>
                    <span class="badge {{ $v->status === 'berlaku' ? 'green' : 'red' }}">{{ $v->status === 'berlaku' ? 'Berlaku' : 'Tidak berlaku' }}</span></div>
                    @if($v->status !== 'berlaku' && $v->alasan_tidak_berlaku)<div class="muted small">{{ $v->alasan_tidak_berlaku }}</div>@endif
                @endforeach
            @endif
        </div></div>
        @endunless
    </div>
</div>
@endsection
