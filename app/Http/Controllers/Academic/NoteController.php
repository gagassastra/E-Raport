<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Concerns\ResolvesClassContext;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\HomeroomNote;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\ReportCardService;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    use ResolvesClassContext;

    public function __construct(private ReportCardService $svc) {}

    public function index(Request $request)
    {
        [$semesters, $semester, $classes, $class] = $this->classContext($request);
        $rows = collect();
        if ($class && $semester) {
            $this->svc->sync($class, $semester);
            $students = Student::whereIn('id', $this->svc->studentIds($class))
                ->when($request->filled('q'), fn ($q) => $q->search($request->query('q')))->orderBy('nama')->get();
            $notes = HomeroomNote::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
            $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
            $rows = $students->map(fn ($s) => (object) ['student' => $s, 'note' => $notes->get($s->id), 'locked' => $locked->has($s->id)]);
        }

        return view('academic.notes', compact('semesters', 'semester', 'classes', 'class', 'rows'));
    }

    private function context(Request $request, Student $student): array
    {
        $semester = Semester::with('academicYear')->findOrFail((int) $request->input('semester_id', $request->query('semester_id')));
        $class = Classroom::forUser($request->user())->findOrFail((int) $request->input('kelas_id', $request->query('kelas_id')));
        abort_unless($class->academic_year_id === $semester->academic_year_id, 404);
        abort_unless($this->svc->studentIds($class)->contains($student->id), 404);

        return [$class, $semester];
    }

    public function edit(Request $request, Student $student)
    {
        [$class, $semester] = $this->context($request, $student);
        $note = HomeroomNote::firstOrNew(['student_id' => $student->id, 'class_id' => $class->id, 'semester_id' => $semester->id]);
        $locked = ReportCard::where('student_id', $student->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->exists() || $semester->academicYear->is_archived;

        return view('academic.note-edit', compact('student', 'class', 'semester', 'note', 'locked'));
    }

    public function update(Request $request, Student $student)
    {
        [$class, $semester] = $this->context($request, $student);
        abort_if($semester->academicYear->is_archived, 403);
        abort_if(ReportCard::where('student_id', $student->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->exists(), 403, 'Rapor sudah diajukan/disahkan sehingga catatan terkunci. Minta pembukaan kembali jika perlu mengubah.');
        $rules = collect(array_keys(HomeroomNote::FIELDS))->mapWithKeys(fn ($f) => [$f => ['nullable', 'string', 'max:2000']])->all();
        $data = $request->validate($rules);
        HomeroomNote::updateOrCreate(
            ['student_id' => $student->id, 'class_id' => $class->id, 'semester_id' => $semester->id],
            $data + ['academic_year_id' => $class->academic_year_id, 'updated_by' => $request->user()->id]
        );
        $this->svc->sync($class, $semester);
        ActivityLogger::log('simpan_catatan', 'catatan', "Menyimpan catatan wali kelas {$student->nama} ({$semester->label}).", $student);

        return redirect()->route('academic.notes.index', ['kelas_id' => $class->id, 'semester_id' => $semester->id])->with('success', "Catatan untuk {$student->nama} tersimpan.");
    }
}
