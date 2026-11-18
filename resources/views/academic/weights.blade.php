@extends('layouts.app')
@section('title', 'Bobot Penilaian')
@section('content')
<div class="page-head"><div><h1>Pengaturan Bobot Penilaian</h1><p>Default sistem menggunakan rata-rata sama rata: <b>(Sumatif + ASTS + ASAS) ÷ 3</b>. Jika menggunakan bobot khusus, total ketiga komponen wajib 100%.</p></div></div>

<div class="card" style="max-width:640px">
    <form class="filters" method="GET">
        <div class="field grow"><label>Cakupan Pengaturan</label>
            <select class="input" name="semester_id" data-autosubmit>
                <option value="">Default Sistem (Semua Semester)</option>
                @foreach($semesters as $s)<option value="{{ $s->id }}" @selected($semesterId === $s->id)>Khusus {{ $s->academicYear->nama }} — Semester {{ ucfirst($s->jenis) }}</option>@endforeach
            </select></div>
    </form>
    <div class="card-b">
        <form method="POST" action="{{ route('academic.weights.update') }}">@csrf @method('PUT')
            <input type="hidden" name="semester_id" value="{{ $semesterId }}">
            <div class="field mb">
                <label>Metode Perhitungan Nilai Akhir</label>
                <select class="input" name="mode" id="w-mode">
                    <option value="rata" @selected(old('mode', $weight->mode) === 'rata')>Sama Rata — (Sumatif + ASTS + ASAS) / 3 (Default)</option>
                    <option value="bobot" @selected(old('mode', $weight->mode) === 'bobot')>Bobot Persentase Khusus (Total 100%)</option>
                </select>
            </div>
            <div class="grid g3">
                <x-field name="bobot_sumatif" type="number" step="0.01" min="0" max="100" label="Bobot Sumatif (%)" :value="$weight->bobot_sumatif" required />
                <x-field name="bobot_asts" type="number" step="0.01" min="0" max="100" label="Bobot ASTS (%)" :value="$weight->bobot_asts" required />
                <x-field name="bobot_asas" type="number" step="0.01" min="0" max="100" label="Bobot ASAS (%)" :value="$weight->bobot_asas" required />
            </div>
            <p class="muted small mt">Hasil Nilai Akhir selalu dibulatkan ke bilangan bulat terdekat. Nilai yang salah satu komponennya masih kosong belum dihitung.</p>
            <button class="btn mt" type="submit">@icon('check') Simpan Pengaturan Bobot</button>
        </form>
    </div>
</div>
@endsection
