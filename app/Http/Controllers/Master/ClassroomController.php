<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Curriculum;
use App\Models\EducationProgram;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $years = AcademicYear::orderByDesc('nama')->get();
        $programs = EducationProgram::orderBy('tingkat_awal')->get();
        $curricula = Curriculum::where('is_active', true)->orderBy('nama')->get();
        $teachers = Teacher::where('is_active', true)->orderBy('nama')->get();
        $editing = $request->filled('edit') ? Classroom::find($request->query('edit')) : null;

        $yearFilter = $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : AcademicYear::active()?->id;

        $classes = Classroom::with(['program', 'academicYear', 'curriculum', 'teacher'])
            ->withCount(['enrollments as siswa_aktif_count' => fn ($q) => $q->whereNotIn('status', ['pindah', 'keluar'])])
            ->when($yearFilter, fn ($q) => $q->where('academic_year_id', $yearFilter))
            ->when($request->filled('program_id'), fn ($q) => $q->where('education_program_id', $request->query('program_id')))
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('education_program_id')
            ->orderBy('tingkat')
            ->orderBy('paralel')
            ->get();

        return view('master.classes', compact('classes', 'years', 'programs', 'curricula', 'teachers', 'editing', 'yearFilter'));
    }

    private function validatedClass(Request $request, ?Classroom $ignore = null): array
    {
        $data = $request->validate([
            'education_program_id' => ['required', 'exists:education_programs,id'],
            'tingkat' => ['required', 'integer'],
            'paralel' => ['nullable', 'string', 'max:20'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'curriculum_id' => ['required', 'exists:curricula,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'nama' => ['nullable', 'string', 'max:180'],
            'status' => ['required', 'in:aktif,nonaktif,arsip'],
        ]);

        $program = EducationProgram::findOrFail((int) $data['education_program_id']);
        $year = AcademicYear::findOrFail((int) $data['academic_year_id']);
        if (! $program->isTingkatValid((int) $data['tingkat'])) {
            throw ValidationException::withMessages([
                'tingkat' => "Tingkat kelas untuk {$program->nama} hanya boleh ".implode(', ', $program->tingkatList()).'.',
            ]);
        }

        $paralel = trim((string) ($data['paralel'] ?? ''));
        $dup = Classroom::where('education_program_id', $program->id)
            ->where('tingkat', (int) $data['tingkat'])
            ->where('academic_year_id', $year->id)
            ->where('paralel', $paralel)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();
        if ($dup) {
            throw ValidationException::withMessages(['tingkat' => 'Rombongan belajar untuk program, tingkat, paralel, dan tahun ajaran tersebut sudah ada.']);
        }

        $data['paralel'] = $paralel;
        $data['nama'] = filled($data['nama'] ?? null)
            ? trim($data['nama'])
            : Classroom::buildName($program, (int) $data['tingkat'], $year, $paralel);

        return $data;
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('classes.manage'), 403);
        $data = $this->validatedClass($request);
        $class = Classroom::create($data);
        ActivityLogger::log('tambah', 'kelas', "Membuat kelas {$class->nama}.", $class);

        return redirect()->route('master.classes.show', $class)->with('success', "Kelas {$class->nama} berhasil dibuat.");
    }

    public function update(Request $request, Classroom $class)
    {
        abort_unless($request->user()->hasPermission('classes.manage'), 403);
        $data = $this->validatedClass($request, $class);
        if ((int) $data['education_program_id'] !== (int) $class->education_program_id && $class->enrollments()->exists()) {
            throw ValidationException::withMessages(['education_program_id' => 'Program kelas tidak dapat diubah karena sudah memiliki peserta didik.']);
        }
        $class->update($data);
        ActivityLogger::log('ubah', 'kelas', "Memperbarui kelas {$class->nama}.", $class);

        return redirect()->route('master.classes.index', ['academic_year_id' => $class->academic_year_id])->with('success', 'Data kelas berhasil diperbarui.');
    }

    public function assignTeacher(Request $request, Classroom $class)
    {
        abort_unless($request->user()->hasPermission('classes.manage'), 403);
        $data = $request->validate(['teacher_id' => ['nullable', 'exists:teachers,id']]);
        $class->update(['teacher_id' => $data['teacher_id'] ?: null]);
        $namaGuru = $class->fresh()->teacher?->nama ?? 'Tidak ada';
        ActivityLogger::log('tetapkan_wali', 'kelas', "Menetapkan Guru/Wali Kelas {$namaGuru} pada {$class->nama}.", $class);

        return back()->with('success', "Guru/Wali Kelas untuk {$class->nama} berhasil diperbarui ({$namaGuru}).");
    }

    public function show(Classroom $class)
    {
        $class->load(['program', 'academicYear', 'curriculum', 'teacher']);
        $enrollments = StudentEnrollment::with('student')
            ->where('class_id', $class->id)
            ->get()
            ->sortBy(fn ($e) => $e->student->nama)
            ->values();

        // Kandidat peserta didik: HANYA dari program pendidikan yang sama (Paket B tidak boleh campur Paket C) dan belum punya kelas aktif di tahun ajaran ini
        $alreadyInYear = StudentEnrollment::where('academic_year_id', $class->academic_year_id)
            ->whereNotIn('status', ['pindah', 'keluar'])
            ->pluck('student_id');

        $candidates = Student::where('education_program_id', $class->education_program_id)
            ->where('status', 'aktif')
            ->whereNotIn('id', $alreadyInYear)
            ->orderBy('nama')
            ->get();

        $teachers = Teacher::where('is_active', true)->orderBy('nama')->get();
        $otherClassesSameProgram = Classroom::where('academic_year_id', $class->academic_year_id)
            ->where('education_program_id', $class->education_program_id)
            ->where('id', '!=', $class->id)
            ->orderBy('tingkat')->get();

        return view('master.class-show', compact('class', 'enrollments', 'candidates', 'teachers', 'otherClassesSameProgram'));
    }

    public function enrollStudents(Request $request, Classroom $class)
    {
        abort_unless($request->user()->hasPermission('classes.manage'), 403);
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $students = Student::whereIn('id', $data['student_ids'])->get();
        foreach ($students as $st) {
            if ((int) $st->education_program_id !== (int) $class->education_program_id) {
                throw ValidationException::withMessages([
                    'student_ids' => "Peserta didik {$st->nama} terdaftar pada program berbeda dan tidak boleh dicampur ke dalam kelas {$class->program->nama}.",
                ]);
            }
        }

        $added = 0;
        DB::transaction(function () use ($students, $class, &$added) {
            foreach ($students as $st) {
                $BusyInYear = StudentEnrollment::where('student_id', $st->id)
                    ->where('academic_year_id', $class->academic_year_id)
                    ->whereNotIn('status', ['pindah', 'keluar'])
                    ->exists();
                if ($BusyInYear) {
                    continue;
                }
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $st->id, 'class_id' => $class->id],
                    ['academic_year_id' => $class->academic_year_id, 'status' => 'aktif', 'tanggal_masuk' => now()->toDateString()]
                );
                $added++;
            }
        });

        ActivityLogger::log('penempatan_siswa', 'kelas', "Menempatkan {$added} peserta didik ke kelas {$class->nama}.", $class);

        return back()->with('success', "{$added} peserta didik berhasil ditempatkan ke {$class->nama}.");
    }

    public function removeStudent(Request $request, Classroom $class, StudentEnrollment $enrollment)
    {
        abort_unless($request->user()->hasPermission('classes.manage'), 403);
        abort_unless($enrollment->class_id === $class->id, 404);

        $locked = ReportCard::where('student_id', $enrollment->student_id)
            ->where('class_id', $class->id)
            ->whereIn('status', ['menunggu_pengesahan', 'disahkan'])
            ->exists();
        if ($locked) {
            return back()->with('error', 'Peserta didik tidak dapat dikeluarkan dari kelas ini karena sudah memiliki rapor yang diajukan/disahkan.');
        }

        $nama = $enrollment->student->nama;
        DB::transaction(function () use ($enrollment, $class) {
            ReportCard::where('student_id', $enrollment->student_id)->where('class_id', $class->id)->delete();
            $enrollment->delete();
        });
        ActivityLogger::log('keluarkan_siswa', 'kelas', "Mengeluarkan {$nama} dari kelas {$class->nama}.", $class);

        return back()->with('success', "{$nama} dikeluarkan dari kelas {$class->nama}.");
    }

    public function destroy(Request $request, Classroom $class)
    {
        abort_unless($request->user()->hasPermission('classes.manage'), 403);
        if ($class->enrollments()->exists()) {
            return back()->with('error', 'Kelas tidak dapat dihapus karena sudah memiliki riwayat peserta didik.');
        }
        $nama = $class->nama;
        $class->delete();
        ActivityLogger::log('hapus', 'kelas', "Menghapus kelas {$nama}.");

        return back()->with('success', "Kelas {$nama} dihapus.");
    }
}
