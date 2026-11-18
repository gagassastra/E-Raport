<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function index()
    {
        $years = AcademicYear::with(['semesters' => fn ($q) => $q->orderBy('jenis')])->orderByDesc('nama')->get();

        return view('master.years', compact('years'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/', 'unique:academic_years,nama'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'aktifkan_semester' => ['nullable', 'in:ganjil,genap'],
        ], ['nama.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2026/2027.']);

        DB::transaction(function () use ($data) {
            $year = AcademicYear::create([
                'nama' => $data['nama'],
                'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                'is_active' => false,
                'is_archived' => false,
            ]);
            $ganjil = Semester::create(['academic_year_id' => $year->id, 'jenis' => 'ganjil', 'nama' => 'Semester Ganjil', 'is_active' => false]);
            $genap = Semester::create(['academic_year_id' => $year->id, 'jenis' => 'genap', 'nama' => 'Semester Genap', 'is_active' => false]);

            if (! empty($data['aktifkan_semester']) || AcademicYear::where('is_active', true)->count() === 0) {
                $target = ($data['aktifkan_semester'] ?? 'ganjil') === 'genap' ? $genap : $ganjil;
                $this->setActiveSemester($target);
            }
            ActivityLogger::log('tambah', 'tahun_ajaran', "Menambahkan tahun ajaran {$year->nama}.", $year);
        });

        return back()->with('success', 'Tahun ajaran beserta Semester Ganjil dan Genap berhasil dibuat. Data tahun ajaran sebelumnya tetap tersimpan.');
    }

    public function update(Request $request, AcademicYear $year)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/', 'unique:academic_years,nama,'.$year->id],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $year->update($data);
        ActivityLogger::log('ubah', 'tahun_ajaran', "Memperbarui tahun ajaran {$year->nama}.", $year);

        return back()->with('success', 'Tahun ajaran diperbarui.');
    }

    public function activateSemester(Semester $semester)
    {
        $this->setActiveSemester($semester);

        return back()->with('success', "Tahun ajaran {$semester->academicYear->nama} Semester ".ucfirst($semester->jenis).' kini aktif untuk operasional.');
    }

    public function deactivate(AcademicYear $year)
    {
        DB::transaction(function () use ($year) {
            $year->update(['is_active' => false]);
            Semester::where('academic_year_id', $year->id)->update(['is_active' => false]);
        });
        ActivityLogger::log('nonaktifkan', 'tahun_ajaran', "Menonaktifkan tahun ajaran {$year->nama}.", $year);

        return back()->with('success', "Tahun ajaran {$year->nama} dinonaktifkan.");
    }

    public function archive(AcademicYear $year)
    {
        DB::transaction(function () use ($year) {
            $newArchiveState = ! $year->is_archived;
            if ($newArchiveState) {
                $year->update(['is_archived' => true, 'is_active' => false]);
                Semester::where('academic_year_id', $year->id)->update(['is_active' => false]);
            } else {
                $year->update(['is_archived' => false]);
            }
        });
        ActivityLogger::log('arsip', 'tahun_ajaran', ($year->fresh()->is_archived ? 'Mengarsipkan' : 'Membuka arsip')." tahun ajaran {$year->nama}.", $year);

        return back()->with('success', "Status arsip tahun ajaran {$year->nama} diperbarui.");
    }

    private function setActiveSemester(Semester $semester): void
    {
        DB::transaction(function () use ($semester) {
            AcademicYear::query()->update(['is_active' => false]);
            Semester::query()->update(['is_active' => false]);
            $semester->academicYear()->update(['is_active' => true, 'is_archived' => false]);
            $semester->update(['is_active' => true]);
            ActivityLogger::log('aktifkan', 'tahun_ajaran', "Mengaktifkan {$semester->academicYear->nama} Semester ".ucfirst($semester->jenis).'.', $semester);
        });
    }
}
