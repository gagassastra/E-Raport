@extends('layouts.app')
@section('title', 'Data Kurikulum')
@section('content')
<div class="page-head"><div><h1>Data Kurikulum</h1><p>Kelola kurikulum yang digunakan pada program Paket B dan Paket C.</p></div></div>
<div class="grid g-main" style="align-items:start">
    <div class="card">
        <div class="card-h"><h3>Daftar Kurikulum</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Kode</th><th>Nama Kurikulum</th><th class="c">Mapel</th><th class="c">Kelas</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($curricula as $c)
                <tr>
                    <td><b>{{ $c->kode }}</b></td>
                    <td><b>{{ $c->nama }}</b>@if($c->deskripsi)<div class="muted small">{{ $c->deskripsi }}</div>@endif</td>
                    <td class="c">{{ $c->subjects_count }}</td>
                    <td class="c">{{ $c->classes_count }}</td>
                    <td><span class="badge {{ $c->is_active ? 'green' : '' }}">{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="actions">
                        <a class="btn light sm" href="{{ route('master.curricula.index', ['edit' => $c->id]) }}">@icon('edit') Edit</a>
                        @if($c->subjects_count === 0 && $c->classes_count === 0)
                            <form method="POST" action="{{ route('master.curricula.destroy', $c) }}" style="display:inline" data-confirm="Hapus kurikulum {{ $c->nama }}?">@csrf @method('DELETE')<button class="btn red sm" type="submit">@icon('trash')</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="layers" text="Belum ada kurikulum." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="card-h"><h3>{{ $editing ? 'Edit Kurikulum' : 'Tambah Kurikulum' }}</h3></div>
        <div class="card-b">
            <form method="POST" action="{{ $editing ? route('master.curricula.update', $editing) : route('master.curricula.store') }}">
                @csrf @if($editing) @method('PUT') @endif
                <div class="grid">
                    <x-field name="kode" label="Kode Kurikulum" :value="$editing?->kode" placeholder="KM" required />
                    <x-field name="nama" label="Nama Kurikulum" :value="$editing?->nama" placeholder="Kurikulum Merdeka" required />
                    <x-field name="deskripsi" type="textarea" label="Deskripsi" :value="$editing?->deskripsi" rows="3" />
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing?->is_active ?? true))> Kurikulum Aktif</label>
                </div>
                <div class="btn-group mt">
                    <button class="btn" type="submit">@icon('check') {{ $editing ? 'Simpan Perubahan' : 'Tambah Kurikulum' }}</button>
                    @if($editing)<a class="btn light" href="{{ route('master.curricula.index') }}">Batal</a>@endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
