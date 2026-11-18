@extends('layouts.app')
@section('title', 'Kenaikan Kelas & Kelulusan')
@section('content')
<div class="page-head"><div><h1>Riwayat Kelas, Kenaikan Kelas & Kelulusan</h1><p>Keputusan kenaikan kelas dan kelulusan ditentukan serta dikonfirmasi oleh lembaga. Data kelas dan rapor tahun sebelumnya tetap tersimpan secara historis.</p></div></div>

<div class="card mb">
    <form class="filters" method="GET">
        <div class="field"><label>Tahun Ajaran Asal</label>
            <select class="input" name="from_year_id" data-autosubmit>
                @foreach($years as $y)<option value="{{ $y->id }}" @selected($fromYear && $fromYear->id === $y->id)>{{ $y->nama }}{{ $y->is_active ? ' (Aktif)' : '' }}</option>@endforeach
            </select></div>
        <div class="field"><label>Kelas Asal</label>
            <select class="input" name="from_class_id" data-autosubmit>
                @foreach($fromClasses as $c)<option value="{{ $c->id }}" @selected($fromClass && $fromClass->id === $c->id)>{{ $c->nama }}</option>@endforeach
            </select></div>
    </form>

    @if(!$fromClass)
        <x-empty icon="arrow-up" text="Pilih tahun ajaran dan kelas asal terlebih dahulu." />
    @elseif($enrollments->isEmpty())
        <x-empty icon="users" text="Belum ada peserta didik dalam kelas ini." />
    @else
        <form method="POST" action="{{ route('master.promotions.process') }}">
            @csrf
            <input type="hidden" name="from_class_id" value="{{ $fromClass->id }}">
            <div class="card-b" style="border-bottom:1px solid var(--line)">
                <div class="grid g3">
                    <div>
                        <div class="muted small">Kelas Asal</div>
                        <b>{{ $fromClass->nama }}</b>
                        <div class="small muted">{{ $isTerminalGrade ? 'Tingkat Akhir — Dapat ditetapkan Lulus atau Tidak Naik' : 'Dapat ditetapkan Naik ke Kelas '.($fromClass->tingkat + 1).' atau Tidak Naik' }}</div>
                    </div>
                    @if(!$isTerminalGrade)
                    <div class="field">
                        <label>Kelas Tujuan Default (Naik ke Kelas {{ $fromClass->tingkat + 1 }})</label>
                        <select class="input" name="default_to_class_naik">
                            <option value="">— Pilih kelas tujuan naik —</option>
                            @foreach($targetClassesNaik as $tc)<option value="{{ $tc->id }}">{{ $tc->nama }} ({{ $tc->academicYear->nama }})</option>@endforeach
                        </select>
                        @if($targetClassesNaik->isEmpty())<div class="hint">Buat dulu kelas Tingkat {{ $fromClass->tingkat + 1 }} pada Tahun Ajaran berikutnya di menu Rombongan Belajar.</div>@endif
                    </div>
                    @endif
                    <div class="field">
                        <label>Kelas Tujuan Default (Tinggal di Kelas {{ $fromClass->tingkat }})</label>
                        <select class="input" name="default_to_class_tinggal">
                            <option value="">— Pilih kelas tujuan mengulang —</option>
                            @foreach($targetClassesTinggal as $tc)<option value="{{ $tc->id }}">{{ $tc->nama }} ({{ $tc->academicYear->nama }})</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-wrap"><table class="table">
                <thead><tr><th>No</th><th>Nama Peserta Didik</th><th>NIS</th><th>Status Saat Ini</th><th>Keputusan Lembaga</th><th>Kelas Tujuan (Opsional per Siswa)</th><th>Catatan</th></tr></thead>
                <tbody>
                @foreach($enrollments as $i => $e)
                    @php($dec = $existingDecisions->get($e->id))
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><a href="{{ route('master.students.show', $e->student) }}"><b>{{ $e->student->nama }}</b></a></td>
                        <td>{{ $e->student->nis }}</td>
                        <td>
                            <span class="badge {{ $e->status === 'aktif' ? 'green' : ($e->status === 'lulus' ? 'purple' : 'blue') }}">{{ \App\Models\StudentEnrollment::STATUS[$e->status] ?? $e->status }}</span>
                            @if($dec)<span class="badge {{ $dec->status === 'dikonfirmasi' ? 'navy' : 'amber' }}">{{ ucfirst($dec->status) }}</span>@endif
                        </td>
                        <td>
                            <select class="input" name="items[{{ $e->id }}][keputusan]">
                                <option value="">— Pilih keputusan —</option>
                                @if(!$isTerminalGrade)
                                    <option value="naik" @selected(($dec?->keputusan ?? '') === 'naik')>Naik ke Kelas {{ $fromClass->tingkat + 1 }}</option>
                                @else
                                    <option value="lulus" @selected(($dec?->keputusan ?? '') === 'lulus')>Lulus {{ $fromClass->program->nama }}</option>
                                @endif
                                <option value="tinggal" @selected(($dec?->keputusan ?? '') === 'tinggal')>Tidak Naik Kelas (Mengulang Kelas {{ $fromClass->tingkat }})</option>
                            </select>
                        </td>
                        <td>
                            <select class="input" name="items[{{ $e->id }}][to_class_id]">
                                <option value="">Ikuti kelas tujuan default</option>
                                @foreach($targetClassesNaik as $tc)<option value="{{ $tc->id }}" @selected($dec?->to_class_id === $tc->id)>Naik: {{ $tc->nama }}</option>@endforeach
                                @foreach($targetClassesTinggal as $tc)<option value="{{ $tc->id }}" @selected($dec?->to_class_id === $tc->id)>Tinggal: {{ $tc->nama }}</option>@endforeach
                            </select>
                        </td>
                        <td><input class="input" name="items[{{ $e->id }}][catatan]" value="{{ $dec?->catatan }}" placeholder="Keterangan keputusan"></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-b flex between items-center wrap gap">
                <span class="muted small">Gunakan "Simpan Draft" untuk menyimpan rencana, atau "Konfirmasi & Terapkan" untuk mengeksekusi penempatan kelas baru secara resmi.</span>
                <div class="btn-group">
                    <button class="btn light" type="submit" name="aksi" value="draft">@icon('edit') Simpan sebagai Draft</button>
                    <button class="btn green" type="submit" name="aksi" value="konfirmasi" data-confirm="Konfirmasi dan terapkan keputusan kenaikan kelas / kelulusan ini? Riwayat kelas lama akan dipertahankan dan peserta didik yang naik/mengulang akan ditempatkan ke kelas tahun ajaran baru." data-confirm-title="Konfirmasi Kenaikan Kelas / Kelulusan" data-confirm-class="green" data-confirm-ok="Ya, Konfirmasi & Terapkan">@icon('check-circle') Konfirmasi & Terapkan Keputusan</button>
                </div>
            </div>
        </form>
    @endif
</div>

@if($fromClass && $sameYearClasses->isNotEmpty() && $enrollments->where('status', 'aktif')->isNotEmpty())
<div class="card mb">
    <div class="card-h"><h3>Pemindahan Kelas (Tahun Ajaran {{ $fromClass->academicYear->nama }} — {{ $fromClass->program->nama }})</h3></div>
    <div class="card-b">
        <form method="POST" action="{{ route('master.promotions.transfer') }}" class="grid g4" style="align-items:end">
            @csrf
            <div class="field">
                <label>Peserta Didik</label>
                <select class="input" name="enrollment_id" required>
                    @foreach($enrollments->where('status', 'aktif') as $e)<option value="{{ $e->id }}">{{ $e->student->nama }} ({{ $e->student->nis }})</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Pindahkan ke Kelas</label>
                <select class="input" name="to_class_id" required>
                    @foreach($sameYearClasses as $sc)<option value="{{ $sc->id }}">{{ $sc->nama }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Alasan / Catatan Pemindahan</label>
                <input class="input" name="catatan" placeholder="Penyesuaian rombel">
            </div>
            <button class="btn sky" type="submit">@icon('refresh') Pindahkan Kelas</button>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-h"><h3>Riwayat Kenaikan Kelas, Kelulusan & Pemindahan</h3></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Waktu</th><th>Peserta Didik</th><th>Kelas Asal</th><th>Keputusan</th><th>Kelas Tujuan</th><th>Status</th><th>Oleh</th></tr></thead>
        <tbody>
        @forelse($history as $h)
            <tr>
                <td class="small muted">{{ $h->updated_at->translatedFormat('j M Y H:i') }}</td>
                <td><b>{{ $h->student->nama }}</b><div class="muted small">{{ $h->student->nis }}</div></td>
                <td>{{ $h->fromClass->nama }}</td>
                <td><span class="badge {{ $h->keputusan === 'lulus' ? 'purple' : ($h->keputusan === 'naik' ? 'green' : 'amber') }}">{{ ucfirst($h->keputusan) }}</span></td>
                <td>{{ $h->toClass?->nama ?? ($h->keputusan === 'lulus' ? 'Alumni / Lulus' : '-') }}</td>
                <td><span class="badge {{ $h->status === 'dikonfirmasi' ? 'green' : 'amber' }}">{{ ucfirst($h->status) }}</span></td>
                <td class="small">{{ $h->pengonfirmasi?->name ?? $h->pembuat?->name ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty icon="clock" text="Belum ada riwayat keputusan kenaikan kelas." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $history->links() }}
</div>
@endsection
