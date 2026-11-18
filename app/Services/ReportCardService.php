<?php

namespace App\Services;

use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\HomeroomNote;
use App\Models\ReportApproval;
use App\Models\ReportCard;
use App\Models\ReportSetting;
use App\Models\ReportVerification;
use App\Models\ReportVersion;
use App\Models\Semester;
use App\Models\StudentEnrollment;
use App\Models\TeacherSignature;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportCardService
{
    public function __construct(private ReportDataBuilder $builder, private ReportPdfService $pdf) {}

    /** ID peserta didik yang termasuk dalam kelas (tidak termasuk yang pindah kelas / keluar). */
    public function studentIds(Classroom $class)
    {
        return StudentEnrollment::where('class_id', $class->id)->whereNotIn('status', ['pindah', 'keluar'])->pluck('student_id');
    }

    /** Pastikan setiap peserta didik di kelas memiliki kartu rapor semester; perbarui status otomatis (belum diisi / sedang dikerjakan). */
    public function sync(Classroom $class, Semester $semester): void
    {
        if ($semester->academic_year_id !== $class->academic_year_id) {
            return;
        }
        $ids = $this->studentIds($class);
        $subjectIds = $class->subjects()->pluck('id');

        $cards = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->get()->keyBy('student_id');
        $hasScore = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)
            ->where(fn ($q) => $q->whereNotNull('sumatif')->orWhereNotNull('asts')->orWhereNotNull('asas'))
            ->pluck('student_id')->unique()->flip();
        $hasAtt = AttendanceRecord::where('class_id', $class->id)->where('semester_id', $semester->id)->pluck('student_id')->flip();
        $hasNote = HomeroomNote::where('class_id', $class->id)->where('semester_id', $semester->id)->pluck('student_id')->flip();

        foreach ($ids as $sid) {
            $card = $cards->get($sid);
            $started = isset($hasScore[$sid]) || isset($hasAtt[$sid]) || isset($hasNote[$sid]);
            if (! $card) {
                ReportCard::create([
                    'student_id' => $sid, 'class_id' => $class->id, 'academic_year_id' => $class->academic_year_id,
                    'semester_id' => $semester->id, 'status' => $started ? 'sedang_dikerjakan' : 'belum_diisi',
                ]);
            } elseif (in_array($card->status, ['belum_diisi', 'sedang_dikerjakan'], true)) {
                $new = $started ? 'sedang_dikerjakan' : 'belum_diisi';
                if ($new !== $card->status) {
                    $card->update(['status' => $new]);
                }
            }
        }
    }

    /** Pemeriksaan kelengkapan rapor. */
    public function completeness(ReportCard $card): array
    {
        $class = $card->classroom ?? Classroom::find($card->class_id);
        $subjects = $class->subjects()->get();
        $missing = [];
        if ($subjects->isEmpty()) {
            $missing[] = 'Belum ada mata pelajaran aktif untuk program, tingkat, dan kurikulum kelas ini.';
        }
        $scores = AssessmentScore::where('student_id', $card->student_id)->where('class_id', $class->id)->where('semester_id', $card->semester_id)->get()->keyBy('subject_id');
        $belum = [];
        foreach ($subjects as $sub) {
            $sc = $scores->get($sub->id);
            if (! $sc || ! $sc->isComplete() || $sc->nilai_akhir === null) {
                $belum[] = $sub->nama;
            }
        }
        if ($belum) {
            $missing[] = 'Nilai belum lengkap: '.implode(', ', $belum).'.';
        }
        if (! AttendanceRecord::where('student_id', $card->student_id)->where('class_id', $class->id)->where('semester_id', $card->semester_id)->exists()) {
            $missing[] = 'Absensi belum diisi.';
        }
        $note = HomeroomNote::where('student_id', $card->student_id)->where('class_id', $class->id)->where('semester_id', $card->semester_id)->first();
        if (! $note || ! $note->isFilled()) {
            $missing[] = 'Catatan wali kelas belum diisi.';
        }

        return ['ok' => ! $missing, 'missing' => $missing, 'mapel_total' => $subjects->count(), 'mapel_lengkap' => $subjects->count() - count($belum)];
    }

    /** Ringkasan progres kelas pada semester tertentu. */
    public function progress(Classroom $class, Semester $semester): array
    {
        $ids = $this->studentIds($class);
        $subjectIds = $class->subjects()->pluck('id');
        $n = $ids->count();
        $m = $subjectIds->count();
        $complete = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)
            ->whereIn('student_id', $ids)->whereIn('subject_id', $subjectIds)
            ->whereNotNull('sumatif')->whereNotNull('asts')->whereNotNull('asas')->count();
        $expected = $n * $m;
        $absen = AttendanceRecord::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('student_id', $ids)->count();
        $catatan = HomeroomNote::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('student_id', $ids)->count();

        return [
            'siswa' => $n, 'mapel' => $m, 'diharapkan' => $expected, 'lengkap' => $complete,
            'belum' => max(0, $expected - $complete),
            'persen' => $expected ? (int) round($complete / $expected * 100) : 0,
            'absensi' => $absen, 'catatan' => $catatan,
        ];
    }

    private function assertGuru(User $user, ReportCard $card): void
    {
        $card->loadMissing('classroom.teacher');
        abort_unless($user->hasRole('guru') && $card->classroom->teacher?->user_id === $user->id, 403, 'Anda bukan Guru/Wali Kelas dari kelas ini.');
    }

    /** Guru/Wali Kelas menandatangani dan mengajukan rapor ke Kepala PKBM. */
    public function submit(ReportCard $card, User $guru): void
    {
        $this->assertGuru($guru, $card);
        if (! in_array($card->status, ['sedang_dikerjakan', 'ditolak', 'dibuka_kembali', 'belum_diisi'], true)) {
            throw ValidationException::withMessages(['status' => 'Status rapor saat ini ('.$card->status_label.') tidak dapat diajukan.']);
        }
        $card->loadMissing('student', 'classroom');
        $chk = $this->completeness($card);
        if (! $chk['ok']) {
            throw ValidationException::withMessages(['kelengkapan' => $card->student->nama.': '.implode(' ', $chk['missing'])]);
        }
        $sig = TeacherSignature::where('user_id', $guru->id)->first();
        if (! $sig) {
            $sig = app(SignatureService::class)->ensureQrSignature($guru);
        }

        DB::transaction(function () use ($card, $guru, $sig) {
            $card->details()->delete();
            $scores = AssessmentScore::with('subject')->where('student_id', $card->student_id)->where('class_id', $card->class_id)->where('semester_id', $card->semester_id)->get();
            foreach ($card->classroom->subjects()->get() as $i => $sub) {
                $sc = $scores->firstWhere('subject_id', $sub->id);
                $card->details()->create([
                    'subject_id' => $sub->id, 'kode_mapel' => $sub->kode, 'nama_mapel' => $sub->nama, 'urutan' => $i + 1,
                    'sumatif' => $sc?->sumatif, 'asts' => $sc?->asts, 'asas' => $sc?->asas, 'nilai_akhir' => $sc?->nilai_akhir, 'deskripsi' => $sc?->deskripsi,
                ]);
            }
            $card->update([
                'status' => 'menunggu_pengesahan', 'guru_user_id' => $guru->id, 'guru_signed_at' => now(),
                'submitted_at' => now(), 'is_locked' => true, 'catatan_perbaikan' => null,
            ]);
            $this->recordApproval($card, 'tanda_tangan_guru', $guru, null, $sig->hash);
            $this->recordApproval($card, 'diajukan', $guru);
            ActivityLogger::log('ajukan', 'rapor', "Mengajukan rapor {$card->student->nama} ke Kepala PKBM.", $card);
        });

        $this->notifyRole('kepala_pkbm', "Rapor {$card->student->nama} ({$card->classroom->nama}) menunggu pengesahan Anda.", route('reports.show', $card));
    }

    /** Kepala PKBM menyetujui: menerbitkan versi resmi, QR, dan PDF terkunci. */
    public function approve(ReportCard $card, User $kepala): ReportVersion
    {
        abort_unless($kepala->hasPermission('reports.approve'), 403);
        if ($card->status !== 'menunggu_pengesahan') {
            throw ValidationException::withMessages(['status' => 'Hanya rapor berstatus "Menunggu Pengesahan" yang dapat disahkan.']);
        }
        $card->loadMissing('student', 'classroom', 'semester');
        if (! ReportSetting::where('semester_id', $card->semester_id)->exists()) {
            throw ValidationException::withMessages(['titimangsa' => 'Titimangsa rapor untuk semester ini belum diatur. Atur terlebih dahulu pada menu Titimangsa Rapor.']);
        }
        $sig = TeacherSignature::where('user_id', $kepala->id)->first();
        if (! $sig) {
            $sig = app(SignatureService::class)->ensureQrSignature($kepala);
        }
        $chk = $this->completeness($card);
        if (! $chk['ok']) {
            throw ValidationException::withMessages(['kelengkapan' => $card->student->nama.': '.implode(' ', $chk['missing'])]);
        }

        $version = DB::transaction(function () use ($card, $kepala, $sig) {
            $versi = (int) ($card->versions()->max('versi') ?? 0) + 1;
            $token = bin2hex(random_bytes(24));
            $data = $this->builder->snapshot($card, $kepala, $versi, $token);

            $version = ReportVersion::create([
                'report_card_id' => $card->id, 'versi' => $versi, 'status' => 'berlaku', 'snapshot' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'dibuat_oleh' => $kepala->id, 'diterbitkan_at' => now(),
            ]);
            ReportVerification::create(['report_version_id' => $version->id, 'report_card_id' => $card->id, 'token' => $token, 'is_valid' => true]);

            $bytes = $this->pdf->render([$data]);
            $path = 'rapor/'.$card->semester_id.'/'.$card->class_id.'/'.$card->student->nis.'-v'.$versi.'-'.substr($token, 0, 8).'.pdf';
            Storage::disk('local')->put($path, $bytes);
            $version->update(['pdf_path' => $path, 'pdf_hash' => hash('sha256', $bytes)]);

            $card->update([
                'status' => 'disahkan', 'is_locked' => true, 'versi_aktif' => $versi, 'kepala_user_id' => $kepala->id,
                'approved_at' => now(), 'catatan_perbaikan' => null,
            ]);
            $this->recordApproval($card, 'disahkan', $kepala, null, $sig->hash, $versi);
            ActivityLogger::log('sahkan', 'rapor', "Mengesahkan rapor {$card->student->nama} (versi {$versi}).", $card, ['versi' => $versi, 'pdf_hash' => $version->pdf_hash]);

            return $version;
        });

        if ($card->guru_user_id) {
            Notification::send(User::find($card->guru_user_id), new GeneralNotification("Rapor {$card->student->nama} telah disahkan Kepala PKBM.", route('reports.show', $card)));
        }

        return $version;
    }

    public function reject(ReportCard $card, User $kepala, string $alasan): void
    {
        abort_unless($kepala->hasPermission('reports.approve'), 403);
        if ($card->status !== 'menunggu_pengesahan') {
            throw ValidationException::withMessages(['status' => 'Hanya rapor berstatus "Menunggu Pengesahan" yang dapat dikembalikan.']);
        }
        $card->loadMissing('student');
        DB::transaction(function () use ($card, $kepala, $alasan) {
            $card->update(['status' => 'ditolak', 'is_locked' => false, 'catatan_perbaikan' => $alasan, 'guru_signed_at' => null, 'submitted_at' => null]);
            $this->recordApproval($card, 'ditolak', $kepala, $alasan);
            ActivityLogger::log('tolak', 'rapor', "Mengembalikan rapor {$card->student->nama} untuk diperbaiki.", $card, ['alasan' => $alasan]);
        });
        if ($card->guru_user_id) {
            Notification::send(User::find($card->guru_user_id), new GeneralNotification("Rapor {$card->student->nama} perlu perbaikan: {$alasan}", route('reports.show', $card)));
        }
    }

    /** Membuka kembali rapor yang disahkan/dibatalkan: versi lama dinyatakan tidak berlaku, pengesahan harus diulang. */
    public function reopen(ReportCard $card, User $user, string $alasan): void
    {
        abort_unless($user->hasPermission('reports.reopen'), 403);
        if (! in_array($card->status, ['disahkan', 'dibatalkan'], true)) {
            throw ValidationException::withMessages(['status' => 'Hanya rapor yang telah disahkan atau dibatalkan yang dapat dibuka kembali.']);
        }
        $card->loadMissing('student');
        DB::transaction(function () use ($card, $user, $alasan) {
            $this->invalidateActiveVersion($card, 'Rapor dibuka kembali: '.$alasan);
            $card->update(['status' => 'dibuka_kembali', 'is_locked' => false, 'guru_signed_at' => null, 'submitted_at' => null, 'approved_at' => null, 'kepala_user_id' => null, 'catatan_perbaikan' => $alasan]);
            $this->recordApproval($card, 'dibuka_kembali', $user, $alasan);
            ActivityLogger::log('buka_kembali', 'rapor', "Membuka kembali rapor {$card->student->nama}.", $card, ['alasan' => $alasan]);
        });
        if ($card->guru_user_id) {
            Notification::send(User::find($card->guru_user_id), new GeneralNotification("Rapor {$card->student->nama} dibuka kembali: {$alasan}", route('reports.show', $card)));
        }
    }

    public function cancel(ReportCard $card, User $user, string $alasan): void
    {
        abort_unless($user->hasPermission('reports.reopen'), 403);
        if (! in_array($card->status, ['disahkan', 'menunggu_pengesahan'], true)) {
            throw ValidationException::withMessages(['status' => 'Rapor dengan status ini tidak dapat dibatalkan.']);
        }
        $card->loadMissing('student');
        DB::transaction(function () use ($card, $user, $alasan) {
            $this->invalidateActiveVersion($card, 'Rapor dibatalkan: '.$alasan);
            $card->update(['status' => 'dibatalkan', 'is_locked' => true, 'catatan_perbaikan' => $alasan]);
            $this->recordApproval($card, 'dibatalkan', $user, $alasan);
            ActivityLogger::log('batalkan', 'rapor', "Membatalkan rapor {$card->student->nama}.", $card, ['alasan' => $alasan]);
        });
    }

    private function invalidateActiveVersion(ReportCard $card, string $alasan): void
    {
        foreach ($card->versions()->where('status', 'berlaku')->get() as $v) {
            $v->update(['status' => 'tidak_berlaku', 'dinyatakan_tidak_berlaku_at' => now(), 'alasan_tidak_berlaku' => mb_substr($alasan, 0, 250)]);
            ReportVerification::where('report_version_id', $v->id)->update(['is_valid' => false, 'revoked_at' => now()]);
        }
    }

    private function recordApproval(ReportCard $card, string $aksi, User $user, ?string $catatan = null, ?string $sigHash = null, ?int $versi = null): void
    {
        ReportApproval::create([
            'report_card_id' => $card->id, 'versi' => $versi ?? $card->versi_aktif, 'aksi' => $aksi, 'user_id' => $user->id,
            'nama_penanda' => $user->name, 'jabatan' => $user->jabatan, 'signature_hash' => $sigHash, 'catatan' => $catatan,
            'ip' => request()?->ip(), 'waktu' => now(),
        ]);
    }

    private function notifyRole(string $slug, string $pesan, string $url): void
    {
        $users = User::whereHas('role', fn ($q) => $q->where('slug', $slug))->where('is_active', true)->get();
        Notification::send($users, new GeneralNotification($pesan, $url));
    }

    /** Berkas PDF resmi (versi berlaku) dan verifikasi hash integritas. */
    public function officialPdf(ReportCard $card): ?array
    {
        $v = $card->activeVersion();
        if (! $v) {
            return null;
        }
        $sheetMtime = @filemtime(resource_path('views/reports/_sheet.blade.php')) ?: 0;
        $needsRegen = ! $v->pdf_path
            || ! Storage::disk('local')->exists($v->pdf_path)
            || ($v->updated_at && $v->updated_at->timestamp < $sheetMtime);

        if ($needsRegen) {
            $data = ReportDataBuilder::ensureQrSignatures($v->data(), $card);
            $bytes = $this->pdf->render([$data]);
            $path = $v->pdf_path ?: ('rapor/'.$card->semester_id.'/'.$card->class_id.'/'.$card->student->nis.'-v'.$v->versi.'.pdf');
            Storage::disk('local')->put($path, $bytes);
            $v->update([
                'snapshot' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'pdf_path' => $path,
                'pdf_hash' => hash('sha256', $bytes),
            ]);

            return ['bytes' => $bytes, 'version' => $v->fresh()];
        }

        $bytes = Storage::disk('local')->get($v->pdf_path);
        if (! hash_equals((string) $v->pdf_hash, hash('sha256', $bytes))) {
            ActivityLogger::log('integritas_gagal', 'rapor', "Hash PDF rapor versi {$v->versi} tidak cocok.", $card);
            abort(500, 'Integritas berkas rapor tidak valid. Hubungi administrator.');
        }

        return ['bytes' => $bytes, 'version' => $v];
    }
}
