@extends('layouts.app')
@section('title', 'Tahun Ajaran & Semester')
@section('content')
<div class="page-head"><div><h1>Tahun Ajaran dan Semester</h1><p>Hanya satu tahun ajaran dan satu semester yang aktif pada waktu yang sama. Tahun ajaran lama tetap tersimpan dan dapat diarsipkan.</p></div></div>

<div class="grid g-main" style="align-items:start">
    <div class="card">
        <div class="card-h"><h3>Daftar Tahun Ajaran</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Tahun Ajaran</th><th>Periode</th><th>Status</th><th>Pilih Semester Aktif</th><th></th></tr></thead>
            <tbody>
            @forelse($years as $y)
                <tr>
                    <td><b>{{ $y->nama }}</b></td>
                    <td class="small muted">{{ $y->tanggal_mulai?->format('d/m/Y') ?? '-' }} s.d. {{ $y->tanggal_selesai?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        @if($y->is_active)<span class="badge green">Aktif</span>@elseif($y->is_archived)<span class="badge dark">Diarsipkan</span>@else<span class="badge">Nonaktif</span>@endif
                    </td>
                    <td>
                        <div class="btn-group">
                        @foreach($y->semesters as $sem)
                            <form method="POST" action="{{ route('master.years.activate-semester', $sem) }}">@csrf
                                <button class="btn sm {{ $sem->is_active ? 'green' : 'light' }}" type="submit">
                                    @if($sem->is_active)@icon('check-circle')@endif {{ ucfirst($sem->jenis) }}
                                </button>
                            </form>
                        @endforeach
                        </div>
                    </td>
                    <td class="actions">
                        <div class="btn-group" style="justify-content:flex-end">
                            @if($y->is_active)
                                <form method="POST" action="{{ route('master.years.deactivate', $y) }}">@csrf<button class="btn light sm" type="submit">Nonaktifkan</button></form>
                            @endif
                            <form method="POST" action="{{ route('master.years.archive', $y) }}" data-confirm="{{ $y->is_archived ? 'Buka kembali arsip tahun ajaran '.$y->nama.'?' : 'Arsipkan tahun ajaran '.$y->nama.'? Data nilai dan rapor pada tahun ajaran yang diarsipkan akan dikunci dari pengubahan.' }}" data-confirm-class="amber" data-confirm-ok="Ya, lanjutkan">@csrf
                                <button class="btn light sm" type="submit">@icon('archive') {{ $y->is_archived ? 'Buka Arsip' : 'Arsipkan' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="calendar" text="Belum ada tahun ajaran." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card"><div class="card-h"><h3>Tambah Tahun Ajaran Baru</h3></div><div class="card-b">
        <form method="POST" action="{{ route('master.years.store') }}">@csrf
            <div class="grid">
                <x-field name="nama" label="Tahun Ajaran" placeholder="2026/2027" required hint="Format YYYY/YYYY, contoh: 2026/2027, 2027/2028." />
                <x-field name="tanggal_mulai" type="date" label="Tanggal Mulai" />
                <x-field name="tanggal_selesai" type="date" label="Tanggal Selesai" />
                <x-field name="aktifkan_semester" type="select" label="Langsung Aktifkan Semester">
                    <option value="">Jangan aktifkan dulu</option>
                    <option value="ganjil" selected>Semester Ganjil</option>
                    <option value="genap">Semester Genap</option>
                </x-field>
            </div>
            <button class="btn block mt" type="submit">@icon('plus') Buat Tahun Ajaran</button>
        </form>
    </div></div>
</div>
@endsection
