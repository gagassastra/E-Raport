<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SchoolProfileController extends Controller
{
    public function edit()
    {
        $school = SchoolProfile::current();
        $kepalaUsers = User::whereHas('role', fn ($q) => $q->where('slug', 'kepala_pkbm'))->where('is_active', true)->orderBy('name')->get();

        return view('master.school', compact('school', 'kepalaUsers'));
    }

    public function update(Request $request)
    {
        $school = SchoolProfile::current();
        $data = $request->validate([
            'nama_lembaga' => ['required', 'string', 'max:180'],
            'jenis_lembaga' => ['required', 'string', 'max:100'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'desa' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:180'],
            'kepala_nama' => ['nullable', 'string', 'max:150'],
            'kepala_identitas_label' => ['nullable', 'string', 'max:30'],
            'kepala_identitas' => ['nullable', 'string', 'max:60'],
            'kepala_user_id' => ['nullable', 'exists:users,id'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $bytes = file_get_contents($file->getRealPath());
            if ($ext === 'png') {
                $bytes = \App\Support\PngNormalizer::toRgbPng($bytes);
            }
            $path = 'school/logo-'.bin2hex(random_bytes(5)).'.'.$ext;
            Storage::disk('local')->put($path, $bytes);
            if ($school->logo_path) {
                Storage::disk('local')->delete($school->logo_path);
            }
            $data['logo_path'] = $path;
        }
        unset($data['logo']);

        $school->update($data);
        ActivityLogger::log('ubah', 'lembaga', 'Memperbarui profil dan identitas lembaga.', $school);

        return back()->with('success', 'Profil dan identitas lembaga berhasil disimpan.');
    }

    public function importFromWebsite()
    {
        try {
            $row = DB::selectOne('SELECT * FROM `pkbm_alqudwah`.`school_profiles` ORDER BY id ASC LIMIT 1');
        } catch (\Throwable) {
            $row = null;
        }
        if (! $row) {
            return back()->with('warning', 'Tabel school_profiles pada database website pkbm_alqudwah masih kosong. Silakan isi langsung melalui formulir di bawah.');
        }
        $school = SchoolProfile::current();
        $school->update(array_filter([
            'nama_lembaga' => $row->nama_sekolah ?? null,
            'npsn' => $row->npsn ?? null,
            'alamat' => $row->alamat ?? null,
            'desa' => $row->desa ?? null,
            'kecamatan' => $row->kecamatan ?? null,
            'kabupaten' => $row->kabupaten ?? null,
            'provinsi' => $row->provinsi ?? null,
            'kode_pos' => $row->kode_pos ?? null,
            'telepon' => $row->telepon ?? null,
            'email' => $row->email ?? null,
            'kepala_nama' => $row->kepala_sekolah ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        ActivityLogger::log('impor', 'lembaga', 'Menyalin identitas lembaga dari database website pkbm_alqudwah.', $school);

        return back()->with('success', 'Identitas lembaga berhasil disalin dari database website.');
    }

    public function logo()
    {
        $school = SchoolProfile::current();
        if ($school->logo_path && Storage::disk('local')->exists($school->logo_path)) {
            $mime = Storage::disk('local')->mimeType($school->logo_path) ?: 'image/png';
            $bytes = Storage::disk('local')->get($school->logo_path);
        } elseif ($defaultFile = SchoolProfile::defaultLogoFile()) {
            $mime = 'image/png';
            $bytes = file_get_contents($defaultFile);
        } else {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
