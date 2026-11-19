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
use App\Models\ReportSetting;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\ReportCardService;
use App\Services\ScoreCalculator;
use App\Support\PngNormalizer;
use App\Support\SimpleXlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** Rekap & Leger Nilai kelas (Guru/Wali Kelas melihat kelas yang diampu; Operator, Kepala PKBM, Super Admin melihat seluruh kelas). */
    public function recap(Request $request)
    {
        $user = $request->user();
        $isGuru = $user->hasRole('guru') && ! $user->hasPermission('scores.view');
        $semesters = Semester::with('academicYear')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $semester = $semesters->firstWhere('id', (int) $request->query('semester_id')) ?? Semester::active() ?? $semesters->first();
        $classes = $semester
            ? Classroom::forUser($user)->where('academic_year_id', $semester->academic_year_id)->with('program', 'teacher', 'curriculum')->orderBy('education_program_id')->orderBy('tingkat')->orderBy('paralel')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->query('kelas_id')) ?? $classes->first();

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

        $leger = ($class && $semester)
            ? $this->buildLegerData($class, $semester)
            : ['subjects' => collect(), 'students' => collect(), 'matrix' => [], 'rows' => collect(), 'subjectStats' => [], 'classTotals' => ['avg_jumlah' => null, 'max_jumlah' => null, 'min_jumlah' => null, 'avg_rata' => null, 'max_rata' => null, 'min_rata' => null]];

        return view('academic.recap', array_merge(
            compact('semesters', 'semester', 'classes', 'class', 'classRecap', 'isGuru'),
            $leger
        ));
    }

    /** Unduh Leger Nilai kelas dalam format Microsoft Excel (.xlsx). */
    public function exportLegerExcel(Request $request)
    {
        [$class, $semester] = $this->resolveLegerClass($request);
        $d = $this->buildLegerData($class, $semester);
        $subjects = $d['subjects'];
        $rows = $d['rows'];
        $subjectStats = $d['subjectStats'];
        $classTotals = $d['classTotals'];
        $school = SchoolProfile::current();

        $excelRows = [
            [['v' => 'LEGER NILAI HASIL BELAJAR PESERTA DIDIK — '.strtoupper($school->nama_lembaga), 's' => 1]],
            [['v' => 'Kelas: '.$class->nama.' ('.$class->program->nama.' - '.$class->program->setara.') | Wali Kelas: '.($class->teacher?->nama ?? '-'), 's' => 2]],
            [['v' => 'Tahun Ajaran: '.$semester->academicYear->nama.' — Semester '.ucfirst($semester->jenis).' | Kurikulum: '.($class->curriculum?->nama ?? '-'), 's' => 0]],
            [],
        ];

        $header = [
            ['v' => 'No', 's' => 3],
            ['v' => 'NIS', 's' => 3],
            ['v' => 'NISN', 's' => 3],
            ['v' => 'Nama Peserta Didik', 's' => 3],
            ['v' => 'L/P', 's' => 3],
        ];
        foreach ($subjects as $sub) {
            $header[] = ['v' => $sub->kode."\n(".$sub->nama.')', 's' => 3];
        }
        $header[] = ['v' => 'Jumlah Nilai', 's' => 3];
        $header[] = ['v' => 'Rata-rata', 's' => 3];
        $header[] = ['v' => 'Peringkat', 's' => 3];
        $header[] = ['v' => 'Sakit', 's' => 3];
        $header[] = ['v' => 'Izin', 's' => 3];
        $header[] = ['v' => 'Alpa', 's' => 3];
        $header[] = ['v' => 'Status Rapor', 's' => 3];
        $excelRows[] = $header;

        foreach ($rows as $i => $r) {
            $line = [
                ['v' => $i + 1, 's' => 5, 't' => 'n'],
                ['v' => (string) $r->student->nis, 's' => 5, 't' => 's'],
                ['v' => (string) ($r->student->nisn ?: '-'), 's' => 5, 't' => 's'],
                ['v' => (string) $r->student->nama, 's' => 4, 't' => 's'],
                ['v' => (string) $r->student->jenis_kelamin, 's' => 5, 't' => 's'],
            ];
            foreach ($subjects as $sub) {
                $val = $r->scores[$sub->id] ?? null;
                $line[] = ['v' => $val !== null ? (int) $val : '', 's' => 5];
            }
            $line[] = ['v' => $r->jumlah !== null ? (int) $r->jumlah : '', 's' => 6];
            $line[] = ['v' => $r->rata_rata !== null ? (float) $r->rata_rata : '', 's' => 6];
            $line[] = ['v' => $r->peringkat !== null ? (int) $r->peringkat : '', 's' => 5];
            $line[] = ['v' => (int) $r->sakit, 's' => 5, 't' => 'n'];
            $line[] = ['v' => (int) $r->izin, 's' => 5, 't' => 'n'];
            $line[] = ['v' => (int) $r->alpa, 's' => 5, 't' => 'n'];
            $line[] = ['v' => (string) $r->status_label, 's' => 5, 't' => 's'];
            $excelRows[] = $line;
        }

        if ($rows->isNotEmpty()) {
            foreach ([
                ['Rata-rata Kelas', 'avg', 'avg_jumlah', 'avg_rata'],
                ['Nilai Tertinggi', 'max', 'max_jumlah', 'max_rata'],
                ['Nilai Terendah', 'min', 'min_jumlah', 'min_rata'],
            ] as [$label, $statKey, $totJmlKey, $totRataKey]) {
                $sumLine = [
                    ['v' => '', 's' => 6],
                    ['v' => '', 's' => 6],
                    ['v' => '', 's' => 6],
                    ['v' => $label, 's' => 6, 't' => 's'],
                    ['v' => '', 's' => 6],
                ];
                foreach ($subjects as $sub) {
                    $v = $subjectStats[$sub->id][$statKey] ?? null;
                    $sumLine[] = ['v' => $v !== null ? (float) $v : '', 's' => 6];
                }
                $sumLine[] = ['v' => $classTotals[$totJmlKey] !== null ? (float) $classTotals[$totJmlKey] : '', 's' => 6];
                $sumLine[] = ['v' => $classTotals[$totRataKey] !== null ? (float) $classTotals[$totRataKey] : '', 's' => 6];
                $sumLine[] = ['v' => '', 's' => 6];
                $sumLine[] = ['v' => '', 's' => 6];
                $sumLine[] = ['v' => '', 's' => 6];
                $sumLine[] = ['v' => '', 's' => 6];
                $sumLine[] = ['v' => '', 's' => 6];
                $excelRows[] = $sumLine;
            }
        }

        $colWidths = [1 => 6, 2 => 15, 3 => 15, 4 => 30, 5 => 7];
        $cIdx = 6;
        foreach ($subjects as $sub) {
            $colWidths[$cIdx++] = 14;
        }
        $colWidths[$cIdx++] = 14;
        $colWidths[$cIdx++] = 13;
        $colWidths[$cIdx++] = 11;
        $colWidths[$cIdx++] = 9;
        $colWidths[$cIdx++] = 9;
        $colWidths[$cIdx++] = 9;
        $colWidths[$cIdx] = 22;

        $bytes = SimpleXlsx::write('Leger Nilai', $excelRows, $colWidths);
        $fileName = 'Leger_Nilai_'.preg_replace('/[^A-Za-z0-9]+/', '_', $class->nama.'_'.ucfirst($semester->jenis)).'.xlsx';
        ActivityLogger::log('ekspor_leger', 'nilai', "Mengunduh Leger Nilai Excel kelas {$class->nama} ({$semester->label}).", $class);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Unduh Leger Nilai kelas dalam format PDF A4 Landscape. */
    public function exportLegerPdf(Request $request)
    {
        [$class, $semester] = $this->resolveLegerClass($request);
        $d = $this->buildLegerData($class, $semester);
        $school = SchoolProfile::current();
        $titimangsa = ReportSetting::where('semester_id', $semester->id)->first();
        $logoDataUri = $this->schoolLogoDataUri($school);

        $bytes = Pdf::loadView('academic.leger-print', array_merge($d, [
            'class' => $class,
            'semester' => $semester,
            'school' => $school,
            'titimangsa' => $titimangsa,
            'logoDataUri' => $logoDataUri,
            'isPdf' => true,
        ]))
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'Helvetica',
                'dpi' => 96,
                'isFontSubsettingEnabled' => true,
            ])
            ->output();

        $fileName = 'Leger_Nilai_'.preg_replace('/[^A-Za-z0-9]+/', '_', $class->nama.'_'.ucfirst($semester->jenis)).'.pdf';
        ActivityLogger::log('unduh_leger_pdf', 'nilai', "Mengunduh Leger Nilai PDF kelas {$class->nama} ({$semester->label}).", $class);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Halaman cetak Leger Nilai kelas (A4 Landscape). */
    public function printLeger(Request $request)
    {
        [$class, $semester] = $this->resolveLegerClass($request);
        $d = $this->buildLegerData($class, $semester);
        $school = SchoolProfile::current();
        $titimangsa = ReportSetting::where('semester_id', $semester->id)->first();
        $logoDataUri = $this->schoolLogoDataUri($school);
        ActivityLogger::log('cetak_leger', 'nilai', "Mencetak Leger Nilai kelas {$class->nama} ({$semester->label}).", $class);

        return view('academic.leger-print', array_merge($d, [
            'class' => $class,
            'semester' => $semester,
            'school' => $school,
            'titimangsa' => $titimangsa,
            'logoDataUri' => $logoDataUri,
            'isPdf' => false,
            'autoprint' => true,
        ]));
    }

    private function resolveLegerClass(Request $request): array
    {
        $user = $request->user();
        $semesters = Semester::with('academicYear')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $semester = $semesters->firstWhere('id', (int) $request->query('semester_id')) ?? Semester::active() ?? $semesters->first();
        abort_unless($semester, 404, 'Semester tidak ditemukan.');

        $classes = Classroom::forUser($user)->where('academic_year_id', $semester->academic_year_id)->with('program', 'teacher', 'curriculum')->orderBy('education_program_id')->orderBy('tingkat')->orderBy('paralel')->get();
        $class = $classes->firstWhere('id', (int) $request->query('kelas_id')) ?? $classes->first();
        abort_unless($class, 404, 'Kelas tidak ditemukan atau Anda tidak memiliki akses ke kelas ini.');

        return [$class, $semester];
    }

    private function buildLegerData(Classroom $class, Semester $semester): array
    {
        $this->svc->sync($class, $semester);
        $subjects = $class->subjects()->get();
        $ids = $this->svc->studentIds($class);
        $students = Student::whereIn('id', $ids)->orderBy('nama')->get();
        $scores = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)->get();
        $attByStudent = AttendanceRecord::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
        $cardByStudent = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');

        $matrix = [];
        $bySubject = [];
        foreach ($scores as $sc) {
            $matrix[$sc->student_id][$sc->subject_id] = $sc->nilai_akhir;
            if ($sc->nilai_akhir !== null) {
                $bySubject[$sc->subject_id][] = (int) $sc->nilai_akhir;
            }
        }

        $rows = collect();
        $allJumlah = [];
        $allRata = [];

        foreach ($students as $st) {
            $stScores = [];
            $vals = [];
            foreach ($subjects as $sub) {
                $v = $matrix[$st->id][$sub->id] ?? null;
                $stScores[$sub->id] = $v;
                if ($v !== null) {
                    $vals[] = (int) $v;
                }
            }
            $jumlah = count($vals) ? array_sum($vals) : null;
            $rata = count($vals) ? round(array_sum($vals) / count($vals), 1) : null;
            if ($jumlah !== null) {
                $allJumlah[] = $jumlah;
            }
            if ($rata !== null) {
                $allRata[] = $rata;
            }
            $att = $attByStudent->get($st->id);
            $card = $cardByStudent->get($st->id);
            $statusKey = $card?->status ?? 'belum_diisi';

            $rows->push((object) [
                'student' => $st,
                'scores' => $stScores,
                'jumlah' => $jumlah,
                'rata_rata' => $rata,
                'peringkat' => null,
                'sakit' => $att?->sakit ?? 0,
                'izin' => $att?->izin ?? 0,
                'alpa' => $att?->alpa ?? 0,
                'status' => $statusKey,
                'status_label' => config("erapor.status_rapor.{$statusKey}.0", $statusKey),
            ]);
        }

        // Hitung peringkat kelas berdasarkan Jumlah Nilai (dan Rata-rata) tertinggi
        $ranked = $rows->filter(fn ($r) => $r->jumlah !== null)->sortByDesc(fn ($r) => [$r->jumlah, $r->rata_rata])->values();
        $prevKey = null;
        $prevRank = 0;
        foreach ($ranked as $idx => $r) {
            $key = $r->jumlah.'|'.$r->rata_rata;
            $rank = ($key === $prevKey) ? $prevRank : ($idx + 1);
            $r->peringkat = $rank;
            $prevKey = $key;
            $prevRank = $rank;
        }

        $subjectStats = [];
        foreach ($subjects as $sub) {
            $arr = $bySubject[$sub->id] ?? [];
            $subjectStats[$sub->id] = [
                'avg' => count($arr) ? round(array_sum($arr) / count($arr), 1) : null,
                'max' => count($arr) ? max($arr) : null,
                'min' => count($arr) ? min($arr) : null,
            ];
        }

        $classTotals = [
            'avg_jumlah' => count($allJumlah) ? round(array_sum($allJumlah) / count($allJumlah), 1) : null,
            'max_jumlah' => count($allJumlah) ? max($allJumlah) : null,
            'min_jumlah' => count($allJumlah) ? min($allJumlah) : null,
            'avg_rata' => count($allRata) ? round(array_sum($allRata) / count($allRata), 1) : null,
            'max_rata' => count($allRata) ? max($allRata) : null,
            'min_rata' => count($allRata) ? min($allRata) : null,
        ];

        return compact('subjects', 'students', 'matrix', 'rows', 'subjectStats', 'classTotals');
    }

    private function schoolLogoDataUri(SchoolProfile $sp): ?string
    {
        if ($sp->logo_path && Storage::disk('local')->exists($sp->logo_path)) {
            $mime = Storage::disk('local')->mimeType($sp->logo_path) ?: 'image/png';
            $bytes = Storage::disk('local')->get($sp->logo_path);
            if ($mime === 'image/png') {
                $bytes = PngNormalizer::toRgbPng($bytes);
            }

            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        }
        if ($defaultFile = SchoolProfile::defaultLogoFile()) {
            $bytes = PngNormalizer::toRgbPng(file_get_contents($defaultFile));

            return 'data:image/png;base64,'.base64_encode($bytes);
        }

        return null;
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
