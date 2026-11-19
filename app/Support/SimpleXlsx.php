<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Pembaca dan pembuat berkas Microsoft Excel (.xlsx) serta CSV tanpa dependensi pustaka eksternal.
 */
class SimpleXlsx
{
    /**
     * Buat berkas .xlsx (OpenXML Spreadsheet) di memori dan kembalikan byte binary-nya.
     *
     * @param  string  $sheetName  Nama lembar kerja
     * @param  array<int, array<int, mixed>>  $rows  Baris data (tiap elemen dapat berupa scalar atau array ['v' => ..., 's' => styleIndex, 't' => 's'|'n', 'f' => formula])
     * @param  array<int, float|int>  $colWidths  Lebar kolom (1-indexed: [1 => 6, 2 => 18, ...])
     */
    public static function write(string $sheetName, array $rows, array $colWidths = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip Excel (.xlsx).');
        }

        $safeSheetName = mb_substr(preg_replace('/[\\\\\\/?*\\[\\]:]+/', ' ', $sheetName) ?: 'Sheet1', 0, 31);

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($safeSheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($rows, $colWidths));

        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /**
     * Baca berkas .xlsx atau .csv menjadi array 2 dimensi (baris x kolom, 0-indexed).
     *
     * @return array<int, array<int, string>>
     */
    public static function read(string $filePath, ?string $originalExt = null): array
    {
        $ext = strtolower($originalExt ?: pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            return self::readCsv($filePath);
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            // Fallback jika file ternyata CSV yang diberi ekstensi lain
            return self::readCsv($filePath);
        }

        $sharedStrings = [];
        if (($ssXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = @simplexml_load_string($ssXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $run) {
                            $text .= (string) $run->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name && preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Lembar kerja Excel tidak ditemukan di dalam berkas.');
        }

        $sx = @simplexml_load_string($sheetXml);
        if (! $sx || ! isset($sx->sheetData)) {
            throw new RuntimeException('Format lembar kerja Excel tidak valid.');
        }

        $rows = [];
        foreach ($sx->sheetData->row as $rowEl) {
            $rowCells = [];
            $maxCol = -1;
            $nextCol = 0;
            foreach ($rowEl->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                $colIdx = $ref !== '' ? self::colRefToIndex($ref) : $nextCol;
                $nextCol = $colIdx + 1;
                $maxCol = max($maxCol, $colIdx);

                $type = (string) ($c['t'] ?? '');
                $val = '';
                if ($type === 's') {
                    $idx = (int) ($c->v ?? -1);
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    if (isset($c->is->t)) {
                        $val = (string) $c->is->t;
                    } elseif (isset($c->is->r)) {
                        foreach ($c->is->r as $run) {
                            $val .= (string) $run->t;
                        }
                    }
                } elseif ($type === 'b') {
                    $val = ((string) $c->v) === '1' ? 'TRUE' : 'FALSE';
                } else {
                    $val = isset($c->v) ? (string) $c->v : '';
                }
                $rowCells[$colIdx] = trim($val);
            }

            if ($maxCol >= 0) {
                $normalized = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $normalized[$i] = $rowCells[$i] ?? '';
                }
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * Baca berkas CSV (mendukung pemisah koma maupun titik koma khas Excel Indonesia).
     *
     * @return array<int, array<int, string>>
     */
    public static function readCsv(string $filePath): array
    {
        $content = (string) file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }
        $first = true;
        while (($row = fgetcsv($handle, 8000, $delimiter)) !== false) {
            if ($first) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? ''));
                $first = false;
            }
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    public static function colIndexToLetter(int $index): string
    {
        $letter = '';
        $n = $index + 1;
        while ($n > 0) {
            $rem = ($n - 1) % 26;
            $letter = chr(65 + $rem).$letter;
            $n = intdiv($n - 1, 26);
        }

        return $letter;
    }

    public static function colRefToIndex(string $cellRef): int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellRef, $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $num = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $num - 1);
    }

    private static function esc(string $s): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function sheetXml(array $rows, array $colWidths): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($colWidths) {
            $xml .= '<cols>';
            foreach ($colWidths as $colIdx => $w) {
                $c = (int) $colIdx;
                $width = (float) $w;
                $xml .= '<col min="'.$c.'" max="'.$c.'" width="'.$width.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach (array_values($rows) as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $cIdx => $cell) {
                $ref = self::colIndexToLetter($cIdx).$rowNum;
                $val = $cell;
                $style = 0;
                $type = null;
                $formula = null;

                if (is_array($cell)) {
                    $val = $cell['v'] ?? '';
                    $style = (int) ($cell['s'] ?? 0);
                    $type = $cell['t'] ?? null;
                    $formula = $cell['f'] ?? null;
                }

                $sAttr = $style > 0 ? ' s="'.$style.'"' : '';

                if ($formula !== null) {
                    $vTag = ($val !== null && $val !== '') ? '<v>'.self::esc((string) $val).'</v>' : '';
                    $xml .= '<c r="'.$ref.'"'.$sAttr.'><f>'.self::esc($formula).'</f>'.$vTag.'</c>';
                } elseif ($val === null || $val === '') {
                    if ($style > 0) {
                        $xml .= '<c r="'.$ref.'"'.$sAttr.'/>';
                    }
                } elseif ($type === 'n' || ($type === null && (is_int($val) || is_float($val)))) {
                    $xml .= '<c r="'.$ref.'"'.$sAttr.'><v>'.(is_float($val) ? rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') : (string) $val).'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"'.$sAttr.'><is><t xml:space="preserve">'.self::esc((string) $val).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * Definisi gaya sel (Style Index):
     * 0 = Default
     * 1 = Judul Utama (Bold 13pt, Navy #0B2A5B)
     * 2 = Subjudul / Info (Bold 10pt)
     * 3 = Header Tabel (Bold 10.5pt, Putih di atas Navy #0B2A5B, Rata Tengah, Border)
     * 4 = Sel Data Teks (Border, Rata Kiri)
     * 5 = Sel Data Rata Tengah (Border, Rata Tengah - untuk No, NIS, Nilai)
     * 6 = Sel Nilai Akhir (Bold, Border, Rata Tengah, Latar Biru Muda #E8F1FC)
     */
    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="10.5"/><color rgb="FF1C2636"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="13"/><color rgb="FF0B2A5B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10.5"/><color rgb="FF0B2A5B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10.5"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0B2A5B"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE8F1FC"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFBFD0E6"/></left>'
            .'<right style="thin"><color rgb="FFBFD0E6"/></right>'
            .'<top style="thin"><color rgb="FFBFD0E6"/></top>'
            .'<bottom style="thin"><color rgb="FFBFD0E6"/></bottom>'
            .'<diagonal/>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }
}
