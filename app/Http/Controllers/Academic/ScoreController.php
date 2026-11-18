<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Concerns\ResolvesClassContext;
use App\Http\Controllers\Controller;
use App\Models\AssessmentScore;
use App\Models\GradeWeight;
use App\Models\ReportCard;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Services\ReportCardService;
use App\Services\ScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    use ResolvesClassContext;

    public function __construct(private ReportCardService $svc) {}

    public function index(Request $request)
    {
        [$semesters, $semester, $classes, $class] = $this->classContext($request);
        $subjects = $class ? $class->subjects()->get() : collect();
        $subject = $subjects->firstWhere('id', (int) $request->query('mapel_id')) ?? $subjects->first();
        $weight = GradeWeight::forSemester($semester?->id);

        $rows = collect();
        $locked = collect();
        if ($class && $semester && $subject) {
            $this->svc->sync($class, $semester);
            $ids = $this->svc->studentIds($class);
            $students = \App\Models\Student::whereIn('id', $ids)->orderBy('nama')->get();
            $scores = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)->where('subject_id', $subject->id)->get()->keyBy('student_id');
            $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
            $rows = $students->map(fn ($s) => (object) ['student' => $s, 'score' => $scores->get($s->id), 'locked' => $locked->has($s->id)]);
        }
        $readonly = ! $semester || $semester->academicYear->is_archived;

        return view('academic.scores', compact('semesters', 'semester', 'classes', 'class', 'subjects', 'subject', 'rows', 'weight', 'readonly'));
    }

    public function store(Request $request)
    {
        [$class, $semester] = $this->resolveEditableClass($request);
        $subject = Subject::findOrFail((int) $request->input('mapel_id'));
        abort_unless($class->subjects()->where('id', $subject->id)->exists(), 422, 'Mata pelajaran bukan bagian dari kelas ini.');

        $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.sumatif' => ['nullable', 'numeric', 'between:0,100'],
            'scores.*.asts' => ['nullable', 'numeric', 'between:0,100'],
            'scores.*.asas' => ['nullable', 'numeric', 'between:0,100'],
            'scores.*.deskripsi' => ['nullable', 'string', 'max:2000'],
        ], [
            'scores.*.sumatif.between' => 'Nilai Sumatif harus berada pada rentang 0–100.',
            'scores.*.asts.between' => 'Nilai ASTS harus berada pada rentang 0–100.',
            'scores.*.asas.between' => 'Nilai ASAS harus berada pada rentang 0–100.',
            '*.numeric' => 'Nilai harus berupa angka.',
        ]);

        $weight = GradeWeight::forSemester($semester->id);
        $validStudents = $this->svc->studentIds($class)->flip();
        $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
        $saved = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $class, $semester, $subject, $weight, $validStudents, $locked, &$saved, &$skipped) {
            foreach ($request->input('scores') as $studentId => $in) {
                $studentId = (int) $studentId;
                if (! isset($validStudents[$studentId])) {
                    continue;
                }
                if ($locked->has($studentId)) {
                    $skipped++;

                    continue;
                }
                $s = ScoreCalculator::parse($in['sumatif'] ?? null);
                $a = ScoreCalculator::parse($in['asts'] ?? null);
                $b = ScoreCalculator::parse($in['asas'] ?? null);
                $desk = isset($in['deskripsi']) ? trim($in['deskripsi']) : null;
                $existing = AssessmentScore::where(['student_id' => $studentId, 'class_id' => $class->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id])->first();
                if (! $existing && $s === null && $a === null && $b === null && ! filled($desk)) {
                    continue;
                }
                AssessmentScore::updateOrCreate(
                    ['student_id' => $studentId, 'class_id' => $class->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id],
                    [
                        'education_program_id' => $class->education_program_id, 'curriculum_id' => $class->curriculum_id, 'academic_year_id' => $class->academic_year_id,
                        'sumatif' => $s, 'asts' => $a, 'asas' => $b, 'nilai_akhir' => ScoreCalculator::nilaiAkhir($s, $a, $b, $weight),
                        'deskripsi' => $desk ?: null, 'updated_by' => $request->user()->id,
                    ]
                );
                $saved++;
            }
        });
        $this->svc->sync($class, $semester);
        ActivityLogger::log('simpan_nilai', 'nilai', "Menyimpan nilai {$subject->nama} kelas {$class->nama} ({$semester->label}): $saved data.", $class, ['mapel' => $subject->nama, 'disimpan' => $saved, 'dilewati_terkunci' => $skipped]);

        $msg = "Nilai {$subject->nama} tersimpan ($saved peserta didik).";
        $resp = back()->with('success', $msg);

        return $skipped ? $resp->with('warning', "$skipped peserta didik dilewati karena rapornya sedang diajukan/disahkan (terkunci).") : $resp;
    }
}
