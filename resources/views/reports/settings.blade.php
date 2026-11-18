@extends('layouts.app')
@section('title', 'Titimangsa Rapor')
@section('content')
<div class="page-head"><div><h1>Titimangsa Rapor</h1><p>Tempat dan tanggal penerbitan rapor untuk setiap tahun ajaran dan semester. Tanggal bebas ditentukan, tidak harus sama dengan tanggal hari ini.</p></div></div>
<div class="alert info">@icon('info')<div>Perubahan titimangsa langsung tercermin pada pratinjau/PDF rapor yang <b>belum disahkan</b>. Rapor yang sudah disahkan memakai titimangsa resmi saat diterbitkan; untuk mengubahnya, rapor harus dibuka kembali melalui prosedur yang tercatat.</div></div>
<div class="grid g2">
@foreach($semesters as $s)
    <div class="card">
        <div class="card-h"><h3>{{ $s->academicYear->nama }} — Semester {{ ucfirst($s->jenis) }}</h3>
            <div class="flex gap">@if($s->is_active)<span class="badge green">Aktif</span>@endif @if($s->reportSetting)<span class="badge blue">{{ $s->reportSetting->titimangsa }}</span>@else<span class="badge red">Belum diatur</span>@endif</div></div>
        <div class="card-b">
            <form method="POST" action="{{ route('reports.settings.update', $s) }}">@csrf @method('PUT')
                <div class="form-grid">
                    <x-field name="tempat" label="Tempat penerbitan" :value="$s->reportSetting?->tempat" required placeholder="mis. nama kabupaten/kota" />
                    <x-field name="tanggal_terbit" type="date" label="Tanggal penerbitan" :value="$s->reportSetting?->tanggal_terbit?->toDateString()" required />
                </div>
                @if(($disahkanPerSemester[$s->id] ?? 0) > 0)<p class="muted small">{{ $disahkanPerSemester[$s->id] }} rapor sudah disahkan pada semester ini dan tidak ikut berubah.</p>@endif
                <div class="mt"><button class="btn" type="submit">Simpan Titimangsa</button></div>
            </form>
        </div>
    </div>
@endforeach
</div>
@if($semesters->isEmpty())<div class="card"><x-empty icon="calendar" text="Belum ada tahun ajaran. Tambahkan pada menu Tahun Ajaran." /></div>@endif
@endsection
