<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfService
{
    /** Render satu atau banyak rapor (array data dari ReportDataBuilder) menjadi PDF A4. */
    public function render(array $reports): string
    {
        foreach ($reports as &$r) {
            $r = ReportDataBuilder::ensureQrSignatures($r);
            $r['qr'] = ! empty($r['verify_url']) ? QrService::svgDataUri($r['verify_url']) : null;
        }
        unset($r);

        return Pdf::loadView('reports.pdf', ['reports' => $reports])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'Helvetica',
                'dpi' => 96,
                'isFontSubsettingEnabled' => true,
            ])
            ->output();
    }

    public static function fileName(array $r): string
    {
        $nama = preg_replace('/[^A-Za-z0-9]+/', '_', $r['siswa']['nama']);
        $thn = str_replace('/', '-', $r['kelas']['tahun_ajaran']);

        return "Rapor_{$nama}_{$r['siswa']['nis']}_{$thn}_{$r['kelas']['semester']}".(empty($r['final']) ? '_PRATINJAU' : '').'.pdf';
    }
}
