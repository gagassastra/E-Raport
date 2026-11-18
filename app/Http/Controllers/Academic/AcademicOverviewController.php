<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Concerns\ResolvesClassContext;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\GradeWeight;
use App\Models\HomeroomNote;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\ReportCardService;
use App\Services\ScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicOverviewController extends Controller
{
    use ResolvesClassContext;

    public function __construct(private ReportCardService $svc) {}

    /** Halaman "Kelas Saya" untuk Guru/Wali Kelas: daftar peserta didik & progres per peserta didik. */
    public function myClass(Request $request)
    {
        [$semesters, $semester, $classes, $class] = $this->classContext($request);
        $students = collect();
        $subjects = collect();
        $progress = null;
        $scoreCountByStudent = collect();
        $attByStudent = collect();
        $noteByStudent = collect();
        $cardByStudent = collect();

        if ($class && $semester) {
            $this->svc->sync($class, $semester);
            $ids = $this->svc->studentIds($class);
            $students = Student::whereIn('id', $ids)->orderBy('nama')->get();
            $subjects = $class->subjects()->get();
            $progress = $this->svc->progress($class, $semester);

            $scoreCountByStudent = AssessmentScore::where('class_id', $class->id)
                ->where('semester_id', $semester->id)
                ->whereIn('subject_id', $subjects->pluck('id'))
                ->whereNotNull('sumatif')->whereNotNull('asts')->whereNotNull('asas')
                ->selectRaw('student_id, count(*) c')->groupBy('student_id')->pluck('c', 'student_id');
            $attByStudent = AttendanceRecord::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
            $noteByStudent = HomeroomNote::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
            $cardByStudent = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
        }

        return view('academic.myclass', compact(
            'semesters', 'semester', 'classes', 'class', 'students', 'subjects',
            'progress', 'scoreCountByStudent', 'attByStudent', 'noteByStudent', 'cardByStudent'
        ));
    }

    /** Rekap nilai seluruh kelas (Operator, Kepala PKBM, Super Admin). */
    public function recap(Request $request)
    {
        $semesters = Semester::with('academicYear')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $semester = $semesters->firstWhere('id', (int) $request->query('semester_id')) ?? Semester::active() ?? $semesters->first();
        $classes = $semester
            ? Classroom::where('academic_year_id', $semester->academic_year_id)->with('program', 'teacher')->orderBy('education_program_id')->orderBy('tingkat')->orderBy('paralel')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->query('kelas_id')) ?? $classes->first();

        $subjects = $class ? $class->subjects()->get() : collect();
        $students = collect();
        $matrix = [];
        $classRecap = collect();

        if ($semester) {
            foreach ($classes as $c) {
                $p = $this->svc->progress($c, $semester);
                $avg = AssessmentScore::where('class_id', $c->id)->where('semester_id', $semester->id)->whereNotNull('nilai_akhir')->avg('nilai_akhir');
                $classRecap->push((object) [
                    'class' => $c,
                    'progress' => $p,
                    'rata_rata' => $avg ? round((float) $avg, 1) : null,
                ]);
            }
        }

        if ($class && $semester) {
            $ids = $this->svc->studentIds($class);
            $students = Student::whereIn('id', $ids)->orderBy('nama')->get();
            $scores = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)->get();
            foreach ($scores as $sc) {
                $matrix[$sc->student_id][$sc->subject_id] = $sc->nilai_akhir;
            }
        }

        return view('academic.recap', compact('semesters', 'semester', 'classes', 'class', 'subjects', 'students', 'matrix', 'classRecap'));
    }

    /** Pengaturan bobot penilaian (Operator / Super Admin). */
    public function weightsEdit(Request $request)
    {
        $semesters = Semester::with('academicYear')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $semesterId = $request->filled('semester_id') ? (int) $request->query('semester_id') : null;
        $weight = $semesterId
            ? (GradeWeight::where('semester_id', $semesterId)->first() ?? GradeWeight::forSemester(null))
            : GradeWeight::forSemester(null);

        return view('academic.weights', compact('semesters', 'semesterId', 'weight'));
    }

    public function weightsUpdate(Request $request)
    {
        $data = $request->validate([
            'semester_id' => ['nullable', 'exists:semesters,id'],
            'mode' => ['required', 'in:rata,bobot'],
            'bobot_sumatif' => ['required_if:mode,bobot', 'numeric', 'min:0', 'max:100'],
            'bobot_asts' => ['required_if:mode,bobot', 'numeric', 'min:0', 'max:100'],
            'bobot_asas' => ['required_if:mode,bobot', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($data['mode'] === 'rata') {
            $bs = 33.34;
            $ba = 33.33;
            $bb = 33.33;
        } else {
            $bs = round((float) $data['bobot_sumatif'], 2);
            $ba = round((float) $data['bobot_asts'], 2);
            $bb = round((float) $data['bobot_asas'], 2);
            if (abs(($bs + $ba + $bb) - 100) > 0.01) {
                throw ValidationException::withMessages(['bobot_sumatif' => 'Total bobot Sumatif + ASTS + ASAS harus tepat 100%.']);
            }
        }

        $semId = $data['semester_id'] ? (int) $data['semester_id'] : null;
        $weight = GradeWeight::updateOrCreate(
            ['semester_id' => $semId],
            ['mode' => $data['mode'], 'bobot_sumatif' => $bs, 'bobot_asts' => $ba, 'bobot_asas' => $bb, 'updated_by' => $request->user()->id]
        );

        // Hitung ulang nilai akhir untuk rapor yang belum diajukan/disahkan pada cakupan semester terkait
        DB::transaction(function () use ($semId) {
            $q = AssessmentScore::query()->whereNotNull('sumatif')->whereNotNull('asts')->whereNotNull('asas');
            if ($semId) {
                $q->where('semester_id', $semId);
            }
            foreach ($q->get() as $sc) {
                $locked = ReportCard::where('student_id', $sc->student_id)->where('semester_id', $sc->semester_id)
                    ->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->exists();
                if (! $locked) {
                    $w = GradeWeight::forSemester($sc->semester_id);
                    $sc->update(['nilai_akhir' => ScoreCalculator::nilaiAkhir($sc->sumatif, $sc->asts, $sc->asas, $w)]);
                }
            }
        });

        ActivityLogger::log('ubah_bobot', 'nilai', 'Memperbarui pengaturan bobot penilaian ('.$data['mode'].').', $weight);

        return redirect()->route('academic.weights.edit', ['semester_id' => $semId])->with('success', 'Pengaturan bobot penilaian disimpan dan nilai akhir yang belum dikunci telah diperbarui.');
    }
}
