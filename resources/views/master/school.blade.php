@extends('layouts.app')
@section('title', 'Data Lembaga')
@section('content')
<div class="page-head">
    <div><h1>Identitas & Profil Lembaga</h1><p>Seluruh informasi resmi lembaga yang tampil pada kop dan pengesahan rapor. Kosongkan kolom yang belum memiliki data resmi.</p></div>
    <form method="POST" action="{{ route('master.school.import') }}">@csrf<button class="btn light" type="submit">@icon('download') Salin dari Database Website (Jika Ada)</button></form>
</div>

<div class="card"><div class="card-b">
    <form method="POST" action="{{ route('master.school.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
        <div class="form-grid">
            <x-field name="nama_lembaga" label="Nama Lembaga" :value="$school->nama_lembaga" required />
            <x-field name="jenis_lembaga" label="Jenis Lembaga" :value="$school->jenis_lembaga" required />
            <x-field name="npsn" label="NPSN" :value="$school->npsn" placeholder="Isi NPSN resmi lembaga" />
            <x-field name="kode_pos" label="Kode Pos" :value="$school->kode_pos" />
            <x-field name="alamat" label="Alamat Jalan / Kampung / RT-RW" :value="$school->alamat" full />
            <x-field name="desa" label="Desa / Kelurahan" :value="$school->desa" />
            <x-field name="kecamatan" label="Kecamatan" :value="$school->kecamatan" />
            <x-field name="kabupaten" label="Kabupaten / Kota" :value="$school->kabupaten" />
            <x-field name="provinsi" label="Provinsi" :value="$school->provinsi" />
            <x-field name="telepon" label="Nomor Telepon" :value="$school->telepon" />
            <x-field name="email" type="email" label="Email Lembaga" :value="$school->email" />
            <x-field name="website" label="Website" :value="$school->website" placeholder="https://..." />
            <div class="field">
                <label>Logo Lembaga (PNG/JPG/SVG, maks. 2 MB)</label>
                <input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml">
                @if($school->logo_path)<div class="hint">Logo saat ini sudah tersimpan. Unggah berkas baru untuk mengganti.</div>@endif
            </div>
            <x-field name="kepala_nama" label="Nama Kepala PKBM" :value="$school->kepala_nama" placeholder="Nama lengkap beserta gelar" />
            <x-field name="kepala_identitas_label" label="Label Identitas Kepala (Opsional)" :value="$school->kepala_identitas_label" placeholder="mis. NIP / NUPTK / NIY" />
            <x-field name="kepala_identitas" label="Nomor Identitas Kepala (Opsional)" :value="$school->kepala_identitas" />
            <x-field name="kepala_user_id" type="select" label="Akun Login Kepala PKBM">
                <option value="">— Pilih akun Kepala PKBM —</option>
                @foreach($kepalaUsers as $u)<option value="{{ $u->id }}" @selected(old('kepala_user_id', $school->kepala_user_id) == $u->id)>{{ $u->name }} ({{ $u->username }})</option>@endforeach
            </x-field>
        </div>
        <div class="mt"><button class="btn" type="submit">@icon('check') Simpan Identitas Lembaga</button></div>
    </form>
</div></div>
@endsection
