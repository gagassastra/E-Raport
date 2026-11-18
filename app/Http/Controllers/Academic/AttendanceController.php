<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Concerns\ResolvesClassContext;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\ReportCard;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\ReportCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ResolvesClassContext;

    public function __construct(private ReportCardService $svc) {}

    public function index(Request $request)
    {
        [$semesters, $semester, $classes, $class] = $this->classContext($request);
        $rows = collect();
        $total = ['sakit' => 0, 'izin' => 0, 'alpa' => 0];
        if ($class && $semester) {
            $this->svc->sync($class, $semester);
            $students = Student::whereIn('id', $this->svc->studentIds($class))
                ->when($request->filled('q'), fn ($q) => $q->search($request->query('q')))->orderBy('nama')->get();
            $att = AttendanceRecord::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
            $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
            $rows = $students->map(fn ($s) => (object) ['student' => $s, 'att' => $att->get($s->id), 'locked' => $locked->has($s->id)]);
            foreach ($att as $a) {
                foreach ($total as $k => $_) {
                    $total[$k] += $a->{$k};
                }
            }
        }
        $readonly = ! $semester || $semester->academicYear->is_archived;

        return view('academic.attendance', compact('semesters', 'semester', 'classes', 'class', 'rows', 'total', 'readonly'));
    }

    public function store(Request $request)
    {
        [$class, $semester] = $this->resolveEditableClass($request);
        $request->validate([
            'abs' => ['required', 'array'],
            'abs.*.sakit' => ['nullable', 'integer', 'between:0,366'],
            'abs.*.izin' => ['nullable', 'integer', 'between:0,366'],
            'abs.*.alpa' => ['nullable', 'integer', 'between:0,366'],
        ], ['abs.*.*.integer' => 'Jumlah absensi harus berupa bilangan bulat.', 'abs.*.*.between' => 'Jumlah absensi harus antara 0 dan 366 hari.']);

        $valid = $this->svc->studentIds($class)->flip();
        $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
        $n = 0;
        DB::transaction(function () use ($request, $class, $semester, $valid, $locked, &$n) {
            foreach ($request->input('abs') as $sid => $in) {
                $sid = (int) $sid;
                if (! isset($valid[$sid]) || $locked->has($sid)) {
                    continue;
                }
                AttendanceRecord::updateOrCreate(
                    ['student_id' => $sid, 'class_id' => $class->id, 'semester_id' => $semester->id],
                    ['academic_year_id' => $class->academic_year_id, 'sakit' => (int) ($in['sakit'] ?? 0), 'izin' => (int) ($in['izin'] ?? 0), 'alpa' => (int) ($in['alpa'] ?? 0), 'updated_by' => $request->user()->id]
                );
                $n++;
            }
        });
        $this->svc->sync($class, $semester);
        ActivityLogger::log('simpan_absensi', 'absensi', "Menyimpan absensi kelas {$class->nama} ({$semester->label}): $n data.", $class);

        return back()->with('success', "Absensi tersimpan ($n peserta didik).");
    }
}
