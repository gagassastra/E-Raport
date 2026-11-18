<?php

namespace App\Services;

use App\Models\GradeWeight;

class ScoreCalculator
{
    /**
     * Nilai akhir: baru dihitung bila ketiga komponen terisi (nilai kosong TIDAK dianggap nol).
     * Mode "rata": (Sumatif + ASTS + ASAS) / 3; mode "bobot": rata-rata tertimbang (total 100%).
     * Hasil dibulatkan ke bilangan bulat terdekat.
     */
    public static function nilaiAkhir(?float $sumatif, ?float $asts, ?float $asas, ?GradeWeight $w = null): ?int
    {
        if ($sumatif === null || $asts === null || $asas === null) {
            return null;
        }
        if ($w && $w->mode === 'bobot') {
            $hasil = ($sumatif * $w->bobot_sumatif + $asts * $w->bobot_asts + $asas * $w->bobot_asas) / 100;
        } else {
            $hasil = ($sumatif + $asts + $asas) / 3;
        }

        return (int) round($hasil, 0, PHP_ROUND_HALF_UP);
    }

    /** Ubah input form ('' / null => null; koma desimal diterima). */
    public static function parse(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        $v = trim(str_replace(',', '.', (string) $v));

        return $v === '' ? null : (float) $v;
    }

    /** Rekomendasi deskripsi (hanya bantuan, guru bebas menyunting) berbasis nilai akhir. */
    public static function rekomendasi(string $mapel, ?int $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }
        $mapel = trim($mapel);
        if ($nilai >= 90) {
            return "Menunjukkan penguasaan yang sangat baik pada seluruh capaian pembelajaran $mapel.";
        }
        if ($nilai >= 80) {
            return "Menunjukkan penguasaan yang baik pada capaian pembelajaran $mapel; perlu terus mengasah ketelitian dan pendalaman materi.";
        }
        if ($nilai >= 70) {
            return "Menunjukkan penguasaan yang cukup pada capaian pembelajaran $mapel; perlu penguatan pada beberapa bagian materi.";
        }
        if ($nilai >= 60) {
            return "Penguasaan capaian pembelajaran $mapel mulai berkembang; perlu bimbingan dan latihan tambahan.";
        }

        return "Perlu pendampingan intensif untuk mencapai kompetensi dasar pada $mapel.";
    }
}
