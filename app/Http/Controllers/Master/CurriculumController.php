<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        $curricula = Curriculum::withCount(['subjects', 'classes'])->orderBy('kode')->get();
        $editing = $request->filled('edit') ? Curriculum::find($request->query('edit')) : null;

        return view('master.curricula', compact('curricula', 'editing'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:curricula,kode'],
            'nama' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $c = Curriculum::create($data);
        ActivityLogger::log('tambah', 'kurikulum', "Menambahkan kurikulum {$c->nama}.", $c);

        return redirect()->route('master.curricula.index')->with('success', 'Kurikulum berhasil ditambahkan.');
    }

    public function update(Request $request, Curriculum $curriculum)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:curricula,kode,'.$curriculum->id],
            'nama' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $curriculum->update($data);
        ActivityLogger::log('ubah', 'kurikulum', "Memperbarui kurikulum {$curriculum->nama}.", $curriculum);

        return redirect()->route('master.curricula.index')->with('success', 'Kurikulum diperbarui.');
    }

    public function destroy(Curriculum $curriculum)
    {
        if ($curriculum->subjects()->exists() || $curriculum->classes()->exists()) {
            return back()->with('error', 'Kurikulum tidak dapat dihapus karena sudah digunakan pada mata pelajaran atau kelas.');
        }
        $nama = $curriculum->nama;
        $curriculum->delete();
        ActivityLogger::log('hapus', 'kurikulum', "Menghapus kurikulum {$nama}.");

        return back()->with('success', 'Kurikulum dihapus.');
    }
}
