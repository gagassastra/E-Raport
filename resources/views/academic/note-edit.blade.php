@extends('layouts.app')
@section('title', 'Catatan Wali Kelas')
@section('content')
<div class="page-head"><div><h1>Catatan untuk {{ $student->nama }}</h1><p>{{ $class->nama }} · Semester {{ ucfirst($semester->jenis) }} {{ $semester->academicYear->nama }}</p></div>
    <a class="btn light" href="{{ route('academic.notes.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id]) }}">Kembali</a></div>
@if($locked)<div class="alert warning">@icon('lock')<div>Catatan terkunci karena rapor sudah diajukan/disahkan atau tahun ajaran diarsipkan.</div></div>@endif
<div class="card"><div class="card-b">
    <form method="POST" action="{{ route('academic.notes.update', $student) }}">@csrf @method('PUT')
        <input type="hidden" name="kelas_id" value="{{ $class->id }}"><input type="hidden" name="semester_id" value="{{ $semester->id }}">
        <div class="form-grid">
            @foreach(\App\Models\HomeroomNote::FIELDS as $f => $label)
                <x-field :name="$f" :label="$label" type="textarea" :value="$note->{$f}" :disabled="$locked" rows="4" full />
            @endforeach
        </div>
        @unless($locked)<div class="mt"><button class="btn" type="submit">@icon('check') Simpan Catatan</button></div>@endunless
    </form>
</div></div>
@endsection
