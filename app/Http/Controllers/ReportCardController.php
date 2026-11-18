<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ReportCard;
use App\Models\ReportSetting;
use App\Models\Semester;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\QrService;
use App\Services\ReportCardService;
use App\Services\ReportDataBuilder;
use App\Services\ReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ReportCardController extends Controller
{
    public function __construct(private ReportCardService $svc, private ReportDataBuilder $builder, private ReportPdfService $pdf) {}

    /* ---------- Otorisasi ---------- */

    private function canView(User $u, ReportCard $card): bool
    {
        if ($u->hasRole('siswa')) {
            return $u->hasPermission('reports.own') && $card->student_id === $u->student?->id && $card->status === 'disahkan';
        }
        if ($u->hasPermission('reports.view_all')) {
            return true;
        }
        if ($u->hasRole('guru') && $u->hasPermission('reports.prepare')) {
            $card->loadMissing('classroom');

            return $card->classroom->teacher_id !== null && $card->classroom->teacher_id === $u->teacher?->id;
        }

        return false;
    }

    private function authorizeCard(Request $r, ReportCard $card): void
    {
        abort_unless($this->canView($r->user(), $card), 403, 'Anda tidak berhak mengakses rapor ini.');
    }

    private function authorizeClass(Request $r, Classroom $class): void
    {
        $u = $r->user();
        $ok = $u->hasPermission('reports.view_all') || ($u->hasRole('guru') && $u->hasPermission('reports.prepare') && $class->teacher_id && $class->teacher_id === $u->teacher?->id);
        abort_unless($ok, 403, 'Anda tidak berhak mengakses kelas ini.');
    }

    private function isGuruOf(User $u, ReportCard $card): bool
    {
        $card->loadMissing('classroom');

        return $u->hasRole('guru') && $card->classroom->teacher_id && $card->classroom->teacher_id === $u->teacher?->id;
    }

    /* ---------- Daftar rapor kelas ---------- */

    public function index(Request $request)
    {
        $user = $request->user();
        $semesters = Semester::with('academicYear')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $semester = $semesters->firstWhere('id', (int) $request->query('semester_id')) ?? Semester::active() ?? $semesters->first();

        $classes = $semester
            ? Classroom::forUser($user)->where('academic_year_id', $semester->academic_year_id)->with('program', 'teacher')->orderBy('tingkat')->orderBy('paralel')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->query('kelas_id')) ?? $classes->first();

        $cards = collect();
        $progress = null;
        $titimangsa = null;
        if ($class && $semester) {
            $this->svc->sync($class, $semester);
            $cards = ReportCard::with('student')->where('class_id', $class->id)->where('semester_id', $semester->id)
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->get()->sortBy(fn ($c) => $c->student->nama)->values();
            foreach ($cards as $c) {
                $c->setRelation('classroom', $class);
                $c->kelengkapan = in_array($c->status, ['disahkan']) ? null : $this->svc->completeness($c);
            }
            $progress = $this->svc->progress($class, $semester);
            $titimangsa = ReportSetting::where('semester_id', $semester->id)->first();
        }
        $statusCount = $class && $semester ? ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status') : collect();

        return view('reports.index', compact('semesters', 'semester', 'classes', 'class', 'cards', 'progress', 'titimangsa', 'statusCount'));
    }

    /* ---------- Pratinjau ---------- */

    public function show(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $user = $request->user();
        $card->load('student.program', 'classroom.program', 'classroom.teacher', 'semester.academicYear', 'versions', 'approvals.user', 'kepala', 'guru');
        $r = $this->builder->forDisplay($card);
        $r['qr'] = ! empty($r['verify_url']) ? QrService::svgDataUri($r['verify_url']) : null;
        $kelengkapan = $card->status === 'disahkan' ? null : $this->svc->completeness($card);
        $titimangsa = ReportSetting::where('semester_id', $card->semester_id)->first();
        $verification = $card->status === 'disahkan' ? $card->verification : null;

        $can = [
            'submit' => $this->isGuruOf($user, $card) && $user->hasPermission('reports.prepare') && in_array($card->status, ['sedang_dikerjakan', 'ditolak', 'dibuka_kembali', 'belum_diisi'], true),
            'approve' => $user->hasPermission('reports.approve') && $card->status === 'menunggu_pengesahan',
            'reopen' => $user->hasPermission('reports.reopen') && in_array($card->status, ['disahkan', 'dibatalkan'], true),
            'cancel' => $user->hasPermission('reports.reopen') && in_array($card->status, ['disahkan', 'menunggu_pengesahan'], true),
            'titimangsa' => $user->hasPermission('reports.settings') && $card->status !== 'disahkan',
            'edit_data' => $this->isGuruOf($user, $card) && $card->isEditableData(),
        ];
        $isSiswa = $user->hasRole('siswa');

        return view('reports.show', compact('card', 'r', 'kelengkapan', 'titimangsa', 'verification', 'can', 'isSiswa'));
    }

    /* ---------- Cetak / PDF ---------- */

    private function reportFor(ReportCard $card): array
    {
        $r = $this->builder->forDisplay($card);
        $r['qr'] = ! empty($r['verify_url']) ? QrService::svgDataUri($r['verify_url']) : null;

        return $r;
    }

    public function print(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        ActivityLogger::log('cetak', 'rapor', "Mencetak rapor {$card->student->nama}.", $card);

        return view('reports.print', ['reports' => [$this->reportFor($card)], 'backUrl' => route('reports.show', $card)]);
    }

    public function pdf(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $card->loadMissing('student');
        $unduh = $request->boolean('unduh', true);

        if ($card->status === 'disahkan' && ($official = $this->svc->officialPdf($card))) {
            $r = $official['version']->data();
            $bytes = $official['bytes'];
        } else {
            abort_if($request->user()->hasRole('siswa'), 403);
            $r = $this->reportFor($card);
            $bytes = $this->pdf->render([$r]);
        }
        ActivityLogger::log($unduh ? 'unduh' : 'lihat_pdf', 'rapor', "Mengakses PDF rapor {$card->student->nama}.", $card);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($unduh ? 'attachment' : 'inline').'; filename="'.ReportPdfService::fileName($r).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ---------- Cetak massal ---------- */

    private function classCards(Request $request, Classroom $class): array
    {
        $this->authorizeClass($request, $class);
        $semester = Semester::findOrFail((int) $request->query('semester_id'));
        abort_unless($semester->academic_year_id === $class->academic_year_id, 404);
        $this->svc->sync($class, $semester);
        $cards = ReportCard::with('student', 'classroom')->where('class_id', $class->id)->where('semester_id', $semester->id)
            ->when($request->boolean('hanya_disahkan'), fn ($q) => $q->where('status', 'disahkan'))
            ->get()->sortBy(fn ($c) => $c->student->nama)->values();
        if ($cards->isEmpty()) {
            throw ValidationException::withMessages(['rapor' => 'Tidak ada rapor yang dapat dicetak untuk filter ini.']);
        }

        return [$cards, $semester];
    }

    public function classPrint(Request $request, Classroom $class)
    {
        [$cards] = $this->classCards($request, $class);
        $reports = $cards->map(fn ($c) => $this->reportFor($c))->all();
        ActivityLogger::log('cetak_massal', 'rapor', "Cetak massal rapor {$class->nama} ({$cards->count()} rapor).", $class);

        return view('reports.print', ['reports' => $reports, 'backUrl' => route('reports.index', ['kelas_id' => $class->id, 'semester_id' => $request->query('semester_id')])]);
    }

    public function classPdf(Request $request, Classroom $class)
    {
        [$cards, $semester] = $this->classCards($request, $class);
        $reports = $cards->map(fn ($c) => $this->reportFor($c))->all();
        $bytes = $this->pdf->render($reports);
        ActivityLogger::log('unduh_massal', 'rapor', "Unduh PDF gabungan rapor {$class->nama} ({$cards->count()} rapor).", $class);
        $name = 'Rapor_'.preg_replace('/[^A-Za-z0-9]+/', '_', $class->nama).'_'.ucfirst($semester->jenis).'.pdf';

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }

    public function classZip(Request $request, Classroom $class)
    {
        [$cards, $semester] = $this->classCards($request, $class);
        $tmp = tempnam(sys_get_temp_dir(), 'rpz');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($cards as $c) {
            if ($c->status === 'disahkan' && ($official = $this->svc->officialPdf($c))) {
                $r = $official['version']->data();
                $bytes = $official['bytes'];
            } else {
                $r = $this->reportFor($c);
                $bytes = $this->pdf->render([$r]);
            }
            $zip->addFromString(ReportPdfService::fileName($r), $bytes);
        }
        $zip->close();
        ActivityLogger::log('unduh_zip', 'rapor', "Unduh ZIP rapor {$class->nama} ({$cards->count()} berkas).", $class);
        $name = 'Rapor_'.preg_replace('/[^A-Za-z0-9]+/', '_', $class->nama).'_'.ucfirst($semester->jenis).'.zip';

        return response()->download($tmp, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    /* ---------- Alur kerja ---------- */

    public function submit(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $this->svc->submit($card, $request->user());

        return redirect()->route('reports.show', $card)->with('success', 'Rapor ditandatangani dan diajukan kepada Kepala PKBM.');
    }

    public function approve(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $v = $this->svc->approve($card, $request->user());

        return redirect()->route('reports.show', $card)->with('success', "Rapor disahkan dan diterbitkan (versi {$v->versi}).");
    }

    public function reject(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:1000']]);
        $this->svc->reject($card, $request->user(), $data['alasan']);

        return redirect()->route('reports.show', $card)->with('success', 'Rapor dikembalikan kepada Guru/Wali Kelas untuk diperbaiki.');
    }

    public function reopen(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:1000']]);
        $this->svc->reopen($card, $request->user(), $data['alasan']);

        return redirect()->route('reports.show', $card)->with('success', 'Rapor dibuka kembali. Versi sebelumnya dinyatakan tidak berlaku; pengesahan harus diulang.');
    }

    public function cancel(Request $request, ReportCard $card)
    {
        $this->authorizeCard($request, $card);
        $data = $request->validate(['alasan' => ['required', 'string', 'max:1000']]);
        $this->svc->cancel($card, $request->user(), $data['alasan']);

        return redirect()->route('reports.show', $card)->with('success', 'Rapor dibatalkan.');
    }

    public function submitAll(Request $request, Classroom $class)
    {
        $this->authorizeClass($request, $class);
        $semester = Semester::findOrFail((int) $request->input('semester_id'));
        $this->svc->sync($class, $semester);
        $ok = 0;
        $gagal = [];
        foreach (ReportCard::with('student', 'classroom')->where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['sedang_dikerjakan', 'ditolak', 'dibuka_kembali'])->get() as $card) {
            try {
                $this->svc->submit($card, $request->user());
                $ok++;
            } catch (ValidationException $e) {
                $gagal[] = collect($e->errors())->flatten()->first();
            }
        }
        $resp = back()->with($ok ? 'success' : 'warning', "$ok rapor berhasil diajukan ke Kepala PKBM.");
        if ($gagal) {
            $resp->with('error', count($gagal).' rapor belum dapat diajukan: '.implode(' | ', array_slice($gagal, 0, 3)).(count($gagal) > 3 ? ' ...' : ''));
        }

        return $resp;
    }

    public function approveAll(Request $request, Classroom $class)
    {
        $this->authorizeClass($request, $class);
        abort_unless($request->user()->hasPermission('reports.approve'), 403);
        $semester = Semester::findOrFail((int) $request->input('semester_id'));
        $ok = 0;
        $gagal = [];
        foreach (ReportCard::with('student', 'classroom')->where('class_id', $class->id)->where('semester_id', $semester->id)->where('status', 'menunggu_pengesahan')->get() as $card) {
            try {
                $this->svc->approve($card, $request->user());
                $ok++;
            } catch (ValidationException $e) {
                $gagal[] = collect($e->errors())->flatten()->first();
            }
        }
        $resp = back()->with($ok ? 'success' : 'warning', "$ok rapor berhasil disahkan.");
        if ($gagal) {
            $resp->with('error', implode(' | ', array_slice($gagal, 0, 3)));
        }

        return $resp;
    }

    /* ---------- Titimangsa ---------- */

    public function settings(Request $request)
    {
        $semesters = Semester::with('academicYear', 'reportSetting')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $disahkanPerSemester = ReportCard::where('status', 'disahkan')->selectRaw('semester_id, count(*) c')->groupBy('semester_id')->pluck('c', 'semester_id');

        return view('reports.settings', compact('semesters', 'disahkanPerSemester'));
    }

    public function settingsUpdate(Request $request, Semester $semester)
    {
        $data = $request->validate([
            'tempat' => ['required', 'string', 'max:100'],
            'tanggal_terbit' => ['required', 'date'],
        ], [], ['tempat' => 'tempat penerbitan', 'tanggal_terbit' => 'tanggal penerbitan']);

        $old = ReportSetting::where('semester_id', $semester->id)->first();
        $setting = ReportSetting::updateOrCreate(['semester_id' => $semester->id], $data + ['updated_by' => $request->user()->id]);
        ActivityLogger::log('ubah', 'titimangsa', "Mengatur titimangsa rapor {$semester->label}: {$setting->titimangsa}.", $setting, ['sebelumnya' => $old?->titimangsa]);
        $sah = ReportCard::where('semester_id', $semester->id)->where('status', 'disahkan')->count();
        $msg = 'Titimangsa disimpan. Perubahan langsung tampil pada pratinjau PDF rapor yang belum disahkan.';
        if ($sah) {
            $msg .= " $sah rapor yang sudah disahkan tetap memakai titimangsa resmi saat penerbitan (ubah melalui prosedur buka kembali).";
        }

        return back()->with('success', $msg);
    }

    /* ---------- Arsip ---------- */

    public function archive(Request $request)
    {
        $user = $request->user();
        $q = ReportCard::with('student', 'classroom.program', 'semester.academicYear')->where('status', 'disahkan');
        if ($user->hasRole('guru') && ! $user->hasPermission('reports.view_all')) {
            $q->whereIn('class_id', Classroom::forUser($user)->pluck('id'));
        }
        $years = AcademicYear::orderByDesc('nama')->get();
        $q->when($request->filled('tahun'), fn ($x) => $x->where('academic_year_id', $request->query('tahun')))
            ->when($request->filled('semester'), fn ($x) => $x->whereHas('semester', fn ($s) => $s->where('jenis', $request->query('semester'))))
            ->when($request->filled('program'), fn ($x) => $x->whereHas('classroom', fn ($s) => $s->where('education_program_id', $request->query('program'))))
            ->when($request->filled('q'), fn ($x) => $x->whereHas('student', fn ($s) => $s->search($request->query('q'))));
        $cards = $q->orderByDesc('approved_at')->paginate(20)->withQueryString();
        $programs = \App\Models\EducationProgram::all();

        return view('reports.archive', compact('cards', 'years', 'programs'));
    }

    /* ---------- Portal peserta didik ---------- */

    public function portal(Request $request)
    {
        $student = $request->user()->student;
        abort_unless($student, 403, 'Akun belum terhubung dengan data peserta didik.');
        $cards = ReportCard::with('classroom.program', 'semester.academicYear')->where('student_id', $student->id)->where('status', 'disahkan')
            ->orderByDesc('academic_year_id')->orderByDesc('semester_id')->get();

        return view('reports.portal', compact('cards', 'student'));
    }
}
