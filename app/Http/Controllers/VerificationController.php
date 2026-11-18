<?php

namespace App\Http\Controllers;

use App\Models\ReportCard;
use App\Models\ReportVerification;
use App\Services\ReportDataBuilder;
use Illuminate\Http\Request;

/**
 * Halaman verifikasi publik (diakses saat QR Code tanda tangan pada rapor dipindai dengan HP).
 * Menampilkan gambar tanda tangan asli penanda tangan (Guru/Wali Kelas & Kepala PKBM) beserta status keaslian rapor.
 */
class VerificationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $v = null;
        if (preg_match('/^[a-f0-9]{48}$/', $token)) {
            $v = ReportVerification::with('version', 'reportCard.student', 'reportCard.classroom.program', 'reportCard.classroom.teacher', 'reportCard.semester.academicYear')->where('token', $token)->first();
        }
        if (! $v) {
            return response()->view('verification.show', ['found' => false], 404);
        }
        $v->increment('jumlah_dicek');
        $v->forceFill(['terakhir_dicek_at' => now()])->save();

        $card = $v->reportCard;
        $data = ReportDataBuilder::ensureQrSignatures($v->version->data(), $card);
        $valid = $v->is_valid && $v->version->status === 'berlaku' && $card->status === 'disahkan' && $card->versi_aktif === $v->version->versi;
        $penanda = in_array($request->query('penanda'), ['wali', 'kepala'], true) ? $request->query('penanda') : null;

        return view('verification.show', [
            'found' => true,
            'valid' => $valid,
            'penanda' => $penanda,
            'nama' => self::maskName($card->student->nama),
            'card' => $card,
            'data' => $data,
            'version' => $v->version,
            'verification' => $v,
        ]);
    }

    /** Verifikasi tanda tangan langsung dari kartu rapor (termasuk saat pratinjau/sebelum disahkan). */
    public function signature(Request $request, ReportCard $card, string $penanda, ReportDataBuilder $builder)
    {
        abort_unless(in_array($penanda, ['wali', 'kepala'], true), 404);
        $card->load('student', 'classroom.program', 'classroom.teacher', 'semester.academicYear');
        $data = $builder->forDisplay($card);
        $valid = $card->status === 'disahkan';

        return view('verification.show', [
            'found' => true,
            'valid' => $valid,
            'penanda' => $penanda,
            'nama' => self::maskName($card->student->nama),
            'card' => $card,
            'data' => $data,
            'version' => $card->activeVersion(),
            'verification' => $card->verification,
        ]);
    }

    /** Nama ditampilkan proporsional: tiap kata hanya sebagian awal yang terlihat. */
    public static function maskName(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))->map(function ($w) {
            $len = mb_strlen($w);
            if ($len <= 2) {
                return $w;
            }
            $show = max(2, (int) ceil($len / 2));

            return mb_substr($w, 0, $show).str_repeat('*', $len - $show);
        })->implode(' ');
    }
}
