<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\EducationProgram;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportCardService $svc) {}

    public function index(Request $request)
    {
        $user = $request->user()->load('role', 'teacher', 'student.program');
        $activeYear = AcademicYear::active();
        $activeSemester = Semester::active();
        $roleSlug = $user->role?->slug;

        if ($roleSlug === 'siswa') {
            $student = $user->student;
            $enrollment = $student?->currentEnrollment()?->load('classroom.program', 'classroom.academicYear');
            $reports = $student
                ? ReportCard::with('classroom.program', 'semester.academicYear')
                    ->where('student_id', $student->id)
                    ->where('status', 'disahkan')
                    ->orderByDesc('academic_year_id')->orderByDesc('semester_id')->get()
                : collect();

            return view('dashboard.index', compact('user', 'roleSlug', 'activeYear', 'activeSemester', 'student', 'enrollment', 'reports'));
        }

        if ($roleSlug === 'guru') {
            $classes = $activeYear
                ? Classroom::forUser($user)->where('academic_year_id', $activeYear->id)->with('program')->orderBy('tingkat')->get()
                : collect();
            $guruSummaries = [];
            $totalSiswa = 0;
            $totalLengkap = 0;
            $totalDiharapkan = 0;
            $totalBelumLengkap = 0;
            $absenSummary = ['sakit' => 0, 'izin' => 0, 'alpa' => 0, 'terisi' => 0];
            $menungguPemeriksaan = 0;
            $sudahDikirim = 0;

            foreach ($classes as $c) {
                if ($activeSemester) {
                    $this->svc->sync($c, $activeSemester);
                    $p = $this->svc->progress($c, $activeSemester);
                    $totalSiswa += $p['siswa'];
                    $totalLengkap += $p['lengkap'];
                    $totalDiharapkan += $p['diharapkan'];
                    $totalBelumLengkap += $p['belum'];

                    $att = AttendanceRecord::where('class_id', $c->id)->where('semester_id', $activeSemester->id)
                        ->selectRaw('count(*) c, coalesce(sum(sakit),0) s, coalesce(sum(izin),0) i, coalesce(sum(alpa),0) a')->first();
                    $absenSummary['terisi'] += (int) ($att->c ?? 0);
                    $absenSummary['sakit'] += (int) ($att->s ?? 0);
                    $absenSummary['izin'] += (int) ($att->i ?? 0);
                    $absenSummary['alpa'] += (int) ($att->a ?? 0);

                    $st = ReportCard::where('class_id', $c->id)->where('semester_id', $activeSemester->id)
                        ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
                    $perlu = ($st['belum_diisi'] ?? 0) + ($st['sedang_dikerjakan'] ?? 0) + ($st['ditolak'] ?? 0) + ($st['dibuka_kembali'] ?? 0);
                    $terkirim = ($st['menunggu_pengesahan'] ?? 0) + ($st['disahkan'] ?? 0);
                    $menungguPemeriksaan += $perlu;
                    $sudahDikirim += $terkirim;

                    $guruSummaries[] = (object) ['class' => $c, 'progress' => $p, 'perlu' => $perlu, 'terkirim' => $terkirim, 'disahkan' => $st['disahkan'] ?? 0];
                }
            }
            $persenNilai = $totalDiharapkan ? (int) round($totalLengkap / $totalDiharapkan * 100) : 0;

            return view('dashboard.index', compact(
                'user', 'roleSlug', 'activeYear', 'activeSemester', 'classes', 'guruSummaries',
                'totalSiswa', 'persenNilai', 'totalBelumLengkap', 'absenSummary', 'menungguPemeriksaan', 'sudahDikirim'
            ));
        }

        // Operator, Kepala PKBM, Super Admin
        $paketB = EducationProgram::where('kode', 'paket_b')->first();
        $paketC = EducationProgram::where('kode', 'paket_c')->first();

        $totalSiswa = Student::where('status', 'aktif')->count();
        $totalGuru = Teacher::where('is_active', true)->count();
        $classQuery = Classroom::query()->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id));
        $totalKelasB = $paketB ? (clone $classQuery)->where('education_program_id', $paketB->id)->count() : 0;
        $totalKelasC = $paketC ? (clone $classQuery)->where('education_program_id', $paketC->id)->count() : 0;
        $totalKelas = $totalKelasB + $totalKelasC;

        $classes = $activeYear
            ? Classroom::where('academic_year_id', $activeYear->id)->with('program', 'teacher')->orderBy('education_program_id')->orderBy('tingkat')->orderBy('paralel')->get()
            : collect();

        if ($activeSemester) {
            foreach ($classes as $c) {
                $this->svc->sync($c, $activeSemester);
            }
        }

        $reportCounts = $activeSemester
            ? ReportCard::where('semester_id', $activeSemester->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')
            : collect();

        $raporBelumSelesai = ($reportCounts['belum_diisi'] ?? 0) + ($reportCounts['sedang_dikerjakan'] ?? 0) + ($reportCounts['ditolak'] ?? 0) + ($reportCounts['dibuka_kembali'] ?? 0);
        $raporMenunggu = $reportCounts['menunggu_pengesahan'] ?? 0;
        $raporDisahkan = $reportCounts['disahkan'] ?? 0;

        $classStats = [];
        foreach ($classes as $c) {
            $p = $activeSemester ? $this->svc->progress($c, $activeSemester) : ['siswa' => 0, 'mapel' => 0, 'persen' => 0, 'lengkap' => 0, 'diharapkan' => 0];
            $avg = $activeSemester
                ? AssessmentScore::where('class_id', $c->id)->where('semester_id', $activeSemester->id)->whereNotNull('nilai_akhir')->avg('nilai_akhir')
                : null;
            $classStats[] = (object) [
                'class' => $c,
                'progress' => $p,
                'rata_rata' => $avg ? round((float) $avg, 1) : 0,
            ];
        }

        $siswaBCount = $paketB ? Student::where('education_program_id', $paketB->id)->where('status', 'aktif')->count() : 0;
        $siswaCCount = $paketC ? Student::where('education_program_id', $paketC->id)->where('status', 'aktif')->count() : 0;

        $chartSiswa = [
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Paket B (Setara SMP)', 'Paket C (Setara SMA)'],
                'datasets' => [[
                    'data' => [$siswaBCount, $siswaCCount],
                    'backgroundColor' => ['#0b2a5b', '#4a90d9'],
                ]],
            ],
        ];

        $chartProgres = [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(fn ($x) => 'Kls '.$x->class->tingkat.($x->class->paralel ? ' '.$x->class->paralel : '').' ('.$x->class->program->nama.')', $classStats),
                'datasets' => [
                    ['label' => 'Progres Nilai (%)', 'data' => array_map(fn ($x) => $x->progress['persen'], $classStats), 'backgroundColor' => '#0b2a5b'],
                    ['label' => 'Rata-rata Nilai Akhir', 'data' => array_map(fn ($x) => $x->rata_rata, $classStats), 'backgroundColor' => '#4a90d9'],
                ],
            ],
            'options' => ['scales' => ['y' => ['beginAtZero' => true, 'max' => 100]]],
        ];

        $pendingReports = $activeSemester
            ? ReportCard::with('student', 'classroom')->where('semester_id', $activeSemester->id)->where('status', 'menunggu_pengesahan')->latest('submitted_at')->limit(10)->get()
            : collect();

        $recentLogs = $roleSlug === 'super_admin' ? ActivityLog::orderByDesc('id')->limit(8)->get() : collect();
        $totalUsers = $roleSlug === 'super_admin' ? User::count() : 0;

        return view('dashboard.index', compact(
            'user', 'roleSlug', 'activeYear', 'activeSemester', 'totalSiswa', 'totalGuru',
            'totalKelasB', 'totalKelasC', 'totalKelas', 'raporBelumSelesai', 'raporMenunggu', 'raporDisahkan',
            'classStats', 'chartSiswa', 'chartProgres', 'pendingReports', 'recentLogs', 'totalUsers'
        ));
    }
}
