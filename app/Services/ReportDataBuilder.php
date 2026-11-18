<?php

namespace App\Services;

use App\Models\AssessmentScore;
use App\Models\AttendanceRecord;
use App\Models\HomeroomNote;
use App\Models\ReportCard;
use App\Models\ReportSetting;
use App\Models\SchoolProfile;
use App\Models\Setting;
use App\Models\TeacherSignature;
use App\Models\User;
use App\Support\PngNormalizer;
use Illuminate\Support\Facades\Storage;

/**
 * Membangun satu struktur data rapor (array) yang dipakai oleh pratinjau, cetak, PDF, maupun snapshot resmi.
 * Pada cetakan/PDF A4, kolom tanda tangan Guru/Wali Kelas dan Kepala PKBM menampilkan QR Code;
 * ketika QR Code tersebut dipindai (scan HP), halaman verifikasi menampilkan gambar tanda tangan asli mereka.
 */
class ReportDataBuilder
{
    /** Struktur rapor "hidup" dari database (belum disahkan). */
    public function live(ReportCard $card): array
    {
        $card->loadMissing('student.program', 'classroom.program', 'classroom.curriculum', 'classroom.teacher.user', 'academicYear', 'semester');
        $c = $card->classroom;
        $s = $card->student;
        $sem = $card->semester;

        $scores = AssessmentScore::where('student_id', $s->id)->where('class_id', $c->id)->where('semester_id', $sem->id)->get()->keyBy('subject_id');
        $nilai = [];
        foreach ($c->subjects()->get() as $i => $sub) {
            $sc = $scores->get($sub->id);
            $nilai[] = [
                'no' => $i + 1, 'kode' => $sub->kode, 'nama' => $sub->nama,
                'sumatif' => $sc?->sumatif, 'asts' => $sc?->asts, 'asas' => $sc?->asas,
                'akhir' => $sc?->nilai_akhir, 'deskripsi' => $sc?->deskripsi,
            ];
        }

        $att = AttendanceRecord::where('student_id', $s->id)->where('class_id', $c->id)->where('semester_id', $sem->id)->first();
        $note = HomeroomNote::where('student_id', $s->id)->where('class_id', $c->id)->where('semester_id', $sem->id)->first();
        $setting = ReportSetting::where('semester_id', $sem->id)->first();
        $school = SchoolProfile::current();

        $waliUserId = $card->guru_user_id ?: $c->teacher?->user_id;
        $waliTtd = $waliUserId ? TeacherSignature::where('user_id', $waliUserId)->first()?->dataUri() : null;
        $waliVerifyUrl = self::baseUrl().route('verification.signature', ['card' => $card->id, 'penanda' => 'wali'], false);

        $wali = [
            'nama' => $c->teacher?->nama ?? '-',
            'identitas_label' => 'NIP/No. ID',
            'identitas' => $c->teacher?->nip,
            'ttd' => $waliTtd,
            'qr_ttd' => QrService::svgDataUri($waliVerifyUrl, 180),
            'ditandatangani_at' => $card->guru_signed_at?->toIso8601String(),
        ];

        $kepalaUserId = $card->kepala_user_id ?: $school->kepala_user_id;
        $kepalaTtd = $kepalaUserId ? TeacherSignature::where('user_id', $kepalaUserId)->first()?->dataUri() : null;
        $kepalaVerifyUrl = self::baseUrl().route('verification.signature', ['card' => $card->id, 'penanda' => 'kepala'], false);
        $kepala = $this->kepala($school, null, $kepalaTtd, $kepalaVerifyUrl);

        return [
            'card_id' => $card->id,
            'versi' => $card->versi_aktif ?: null,
            'status' => $card->status,
            'final' => false,
            'lembaga' => $this->lembaga($school),
            'siswa' => [
                'nama' => $s->nama,
                'nis' => $s->nis,
                'nisn' => $s->nisn,
                'jk' => $s->jenis_kelamin,
                'jk_label' => $s->jenis_kelamin === 'L' ? 'Laki-laki' : ($s->jenis_kelamin === 'P' ? 'Perempuan' : '-'),
                'tempat_lahir' => $s->tempat_lahir,
                'tanggal_lahir' => $s->tanggal_lahir?->translatedFormat('j F Y'),
                'ttl' => trim(($s->tempat_lahir ?? '').($s->tanggal_lahir ? ', '.$s->tanggal_lahir->translatedFormat('j F Y') : ''), ', '),
                'agama' => $s->agama,
                'alamat' => $s->alamat,
                'nama_wali' => $s->nama_wali,
                'telepon' => $s->telepon,
                'tahun_masuk' => $s->tahun_masuk,
                'status' => \App\Models\Student::STATUS[$s->status] ?? ucfirst((string) $s->status),
            ],
            'kelas' => [
                'nama' => $c->nama, 'program' => $c->program->nama, 'setara' => $c->program->setara, 'tingkat' => $c->tingkat,
                'tahun_ajaran' => $card->academicYear->nama, 'semester' => ucfirst($sem->jenis), 'kurikulum' => $c->curriculum->nama,
            ],
            'nilai' => $nilai,
            'rata_rata' => $this->rataRata($nilai),
            'absensi' => ['sakit' => $att?->sakit ?? 0, 'izin' => $att?->izin ?? 0, 'alpa' => $att?->alpa ?? 0, 'terisi' => (bool) $att],
            'catatan' => $note ? collect(HomeroomNote::FIELDS)->map(fn ($label, $k) => ['label' => $label, 'isi' => $note->{$k}])->filter(fn ($x) => filled($x['isi']))->values()->all() : [],
            'titimangsa' => $setting ? [
                'tempat' => $setting->tempat, 'tanggal' => $setting->tanggal_terbit->toDateString(),
                'teks' => $setting->titimangsa,
            ] : null,
            'wali' => $wali,
            'kepala' => $kepala,
            'token' => null, 'verify_url' => null, 'diterbitkan_at' => null,
        ];
    }

    /** Snapshot resmi saat pengesahan oleh Kepala PKBM. */
    public function snapshot(ReportCard $card, User $kepala, int $versi, string $token): array
    {
        $d = $this->live($card);
        $school = SchoolProfile::current();
        $sigKepala = TeacherSignature::where('user_id', $kepala->id)->first();
        $verifyUrl = self::verifyUrl($token);

        $d['final'] = true;
        $d['status'] = 'disahkan';
        $d['versi'] = $versi;
        $d['token'] = $token;
        $d['verify_url'] = $verifyUrl;
        $d['diterbitkan_at'] = now()->toIso8601String();

        // QR Tanda Tangan Wali Kelas & Kepala PKBM mengarah ke halaman verifikasi yang menampilkan tanda tangan asli mereka
        $d['wali']['qr_ttd'] = QrService::svgDataUri($verifyUrl.'?penanda=wali', 180);
        $d['kepala'] = $this->kepala($school, $kepala, $sigKepala?->dataUri(), $verifyUrl.'?penanda=kepala');
        $d['kepala']['ditandatangani_at'] = now()->toIso8601String();

        return $d;
    }

    /** Data rapor yang akan ditampilkan: snapshot versi berlaku jika sudah disahkan, jika tidak data hidup. */
    public function forDisplay(ReportCard $card): array
    {
        if ($card->status === 'disahkan' && ($v = $card->activeVersion())) {
            $data = $v->data();
            if ($data) {
                return self::ensureQrSignatures($data, $card);
            }
        }

        return self::ensureQrSignatures($this->live($card), $card);
    }

    /** Pastikan setiap data rapor memiliki QR Code untuk tanda tangan Wali Kelas & Kepala PKBM serta data tanda tangan aslinya. */
    public static function ensureQrSignatures(array $r, ?ReportCard $card = null): array
    {
        $verifyUrl = $r['verify_url'] ?? null;
        $cardId = $r['card_id'] ?? $card?->id;

        if ($card) {
            if (empty($r['wali']['ttd']) && ($wid = $card->guru_user_id ?: $card->classroom?->teacher?->user_id)) {
                $r['wali']['ttd'] = TeacherSignature::where('user_id', $wid)->first()?->dataUri();
            }
            if (empty($r['kepala']['ttd']) && ($kid = $card->kepala_user_id ?: SchoolProfile::current()->kepala_user_id)) {
                $r['kepala']['ttd'] = TeacherSignature::where('user_id', $kid)->first()?->dataUri();
            }
        }

        $payloadWali = $verifyUrl
            ? $verifyUrl.'?penanda=wali'
            : ($cardId ? self::baseUrl().route('verification.signature', ['card' => $cardId, 'penanda' => 'wali'], false) : config('app.url'));
        $r['wali']['qr_ttd'] = QrService::svgDataUri($payloadWali, 180);

        $payloadKepala = $verifyUrl
            ? $verifyUrl.'?penanda=kepala'
            : ($cardId ? self::baseUrl().route('verification.signature', ['card' => $cardId, 'penanda' => 'kepala'], false) : config('app.url'));
        $r['kepala']['qr_ttd'] = QrService::svgDataUri($payloadKepala, 180);

        return $r;
    }

    public static function baseUrl(): string
    {
        return rtrim(Setting::get('verification_base_url') ?: config('app.url'), '/');
    }

    public static function verifyUrl(string $token): string
    {
        return self::baseUrl().route('verification.show', ['token' => $token], false);
    }

    private function lembaga(SchoolProfile $sp): array
    {
        $logo = null;
        if ($sp->logo_path && Storage::disk('local')->exists($sp->logo_path)) {
            $mime = Storage::disk('local')->mimeType($sp->logo_path) ?: 'image/png';
            $bytes = Storage::disk('local')->get($sp->logo_path);
            if ($mime === 'image/png') {
                $bytes = PngNormalizer::toRgbPng($bytes);
            }
            $logo = 'data:'.$mime.';base64,'.base64_encode($bytes);
        } elseif ($defaultFile = SchoolProfile::defaultLogoFile()) {
            $bytes = PngNormalizer::toRgbPng(file_get_contents($defaultFile));
            $logo = 'data:image/png;base64,'.base64_encode($bytes);
        }

        return [
            'nama' => $sp->nama_lembaga,
            'jenis' => $sp->jenis_lembaga,
            'npsn' => $sp->npsn,
            'alamat' => $sp->alamat_lengkap,
            'alamat_jalan' => $sp->alamat,
            'desa' => $sp->desa,
            'kecamatan' => $sp->kecamatan,
            'kabupaten' => $sp->kabupaten,
            'provinsi' => $sp->provinsi,
            'kode_pos' => $sp->kode_pos,
            'telepon' => $sp->telepon,
            'email' => $sp->email,
            'website' => $sp->website,
            'kepala_nama' => $sp->kepala_nama,
            'kepala_identitas_label' => $sp->kepala_identitas_label ?: 'NIP/No. ID',
            'kepala_identitas' => $sp->kepala_identitas,
            'logo' => $logo,
        ];
    }

    private function kepala(SchoolProfile $sp, ?User $user, ?string $ttd, string $qrUrl): array
    {
        return [
            'nama' => $sp->kepala_nama ?: ($user?->name ?? '................................'),
            'identitas_label' => $sp->kepala_identitas_label ?: 'NIP/No. ID',
            'identitas' => $sp->kepala_identitas,
            'jabatan' => 'Kepala '.$sp->nama_lembaga,
            'ttd' => $ttd,
            'qr_ttd' => QrService::svgDataUri($qrUrl, 180),
            'ditandatangani_at' => null,
        ];
    }

    private function rataRata(array $nilai): ?float
    {
        $akhir = array_filter(array_column($nilai, 'akhir'), fn ($x) => $x !== null);

        return $nilai && count($akhir) === count($nilai) ? round(array_sum($akhir) / count($akhir), 1) : null;
    }
}
