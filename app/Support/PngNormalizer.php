<?php

namespace App\Support;

/**
 * Konverter PNG RGBA/Grayscale-Alpha ke PNG 24-bit RGB (di atas latar putih) murni PHP (zlib),
 * sehingga DomPDF dapat merender logo/tanda tangan PNG meskipun proses server PHP berjalan tanpa ekstensi GD.
 */
class PngNormalizer
{
    public static function toRgbPng(string $bytes): string
    {
        $sig = "\x89PNG\r\n\x1a\n";
        if ( substr($bytes, 0, 8) !== $sig) {
            return $bytes;
        }

        $len = strlen($bytes);
        $p = 8;
        $w = $h = $bitDepth = $colorType = $interlace = 0;
        $idat = '';

        while ($p + 8 <= $len) {
            $chunkLen = unpack('N', substr($bytes, $p, 4))[1];
            $type = substr($bytes, $p + 4, 4);
            $data = substr($bytes, $p + 8, $chunkLen);
            $p += 12 + $chunkLen;

            if ($type === 'IHDR') {
                $ihdr = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccomp/Cfilter/Cinterlace', $data);
                $w = $ihdr['width'];
                $h = $ihdr['height'];
                $bitDepth = $ihdr['bitDepth'];
                $colorType = $ihdr['colorType'];
                $interlace = $ihdr['interlace'];
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        // Jika sudah RGB (2), Grayscale (0), atau Palette (3), atau bukan 8-bit non-interlace, kembalikan apa adanya
        if (! in_array($colorType, [4, 6], true) || $bitDepth !== 8 || $interlace !== 0 || $w <= 0 || $h <= 0) {
            return $bytes;
        }

        $raw = @zlib_decode($idat);
        if ($raw === false) {
            $raw = @gzuncompress($idat);
        }
        if ($raw === false) {
            return $bytes;
        }

        $bpp = $colorType === 6 ? 4 : 2;
        $stride = $w * $bpp;
        if (strlen($raw) < ($stride + 1) * $h) {
            return $bytes;
        }

        $prevLine = array_fill(0, $stride, 0);
        $curLine = array_fill(0, $stride, 0);
        $outRaw = '';
        $pos = 0;

        for ($y = 0; $y < $h; $y++) {
            $filter = ord($raw[$pos++]);
            for ($i = 0; $i < $stride; $i++) {
                $x = ord($raw[$pos++]);
                $a = $i >= $bpp ? $curLine[$i - $bpp] : 0;
                $b = $prevLine[$i];
                $c = $i >= $bpp ? $prevLine[$i - $bpp] : 0;

                $val = match ($filter) {
                    1 => ($x + $a) & 0xFF,
                    2 => ($x + $b) & 0xFF,
                    3 => ($x + (($a + $b) >> 1)) & 0xFF,
                    4 => ($x + self::paeth($a, $b, $c)) & 0xFF,
                    default => $x,
                };
                $curLine[$i] = $val;
            }

            // Tulis baris RGB (filter 0) dengan komposit alpha di atas latar putih (255,255,255)
            $rowBytes = ["\x00"];
            if ($colorType === 6) {
                for ($i = 0; $i < $stride; $i += 4) {
                    $r = $curLine[$i];
                    $g = $curLine[$i + 1];
                    $b = $curLine[$i + 2];
                    $al = $curLine[$i + 3];
                    if ($al === 255) {
                        $rowBytes[] = chr($r).chr($g).chr($b);
                    } elseif ($al === 0) {
                        $rowBytes[] = "\xFF\xFF\xFF";
                    } else {
                        $inv = 255 - $al;
                        $rowBytes[] = chr((int) (($r * $al + 255 * $inv) / 255))
                            .chr((int) (($g * $al + 255 * $inv) / 255))
                            .chr((int) (($b * $al + 255 * $inv) / 255));
                    }
                }
            } else {
                for ($i = 0; $i < $stride; $i += 2) {
                    $g = $curLine[$i];
                    $al = $curLine[$i + 1];
                    $c = (int) (($g * $al + 255 * (255 - $al)) / 255);
                    $ch = chr($c);
                    $rowBytes[] = $ch.$ch.$ch;
                }
            }
            $outRaw .= implode('', $rowBytes);
            $prevLine = $curLine;
        }

        $newIhdr = pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);
        $newIdat = gzcompress($outRaw, 6);

        return $sig
            .self::chunk('IHDR', $newIhdr)
            .self::chunk('IDAT', $newIdat)
            .self::chunk('IEND', '');
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
