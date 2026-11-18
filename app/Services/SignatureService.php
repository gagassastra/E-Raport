<?php

namespace App\Services;

use App\Models\TeacherSignature;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SignatureService
{
    /** Buat / perbarui tanda tangan digital berbentuk QR Code untuk pengguna. */
    public function ensureQrSignature(User $user): TeacherSignature
    {
        $payload = 'TTE '.$user->jabatan.' | '.$user->name.' | ID:'.$user->id.' | '.config('app.url');
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd
        );
        $svg = (new \BaconQrCode\Writer($renderer))->writeString($payload);

        return $this->persist($user, 'qr', 'image/svg+xml', 'svg', $svg);
    }

    /** Simpan tanda tangan gambar (SVG vektor dari kanvas). SVG dibangun ulang dari data path agar aman. */
    public function storeDrawn(User $user, string $svg): TeacherSignature
    {
        if (! preg_match_all('/\sd="([MLZ0-9 .,\-]+)"/', $svg, $m) || empty($m[1])) {
            throw ValidationException::withMessages(['svg' => 'Data tanda tangan tidak valid. Silakan gambar ulang.']);
        }
        $paths = '';
        foreach (array_slice($m[1], 0, 20) as $d) {
            if (strlen($d) > 400000) {
                throw ValidationException::withMessages(['svg' => 'Tanda tangan terlalu kompleks.']);
            }
            $paths .= '<path d="'.$d.'" fill="none" stroke="#0b1b3a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>';
        }
        $clean = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 200" width="560" height="200">'.$paths.'</svg>';

        return $this->persist($user, 'gambar', 'image/svg+xml', 'svg', $clean);
    }

    public function storeUploaded(User $user, UploadedFile $file): TeacherSignature
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw ValidationException::withMessages(['file' => 'Berkas harus berupa gambar PNG atau JPG.']);
        }
        $isPng = $info[2] === IMAGETYPE_PNG;
        if (extension_loaded('gd')) {
            // Normalisasi: ukuran dibatasi, dienkode ulang (menghapus metadata/muatan berbahaya)
            $img = $isPng ? imagecreatefrompng($file->getRealPath()) : imagecreatefromjpeg($file->getRealPath());
            $w = imagesx($img);
            $h = imagesy($img);
            $max = 700;
            if ($w > $max) {
                $img = imagescale($img, $max);
            }
            imagesavealpha($img, true);
            ob_start();
            imagepng($img);
            $bytes = ob_get_clean();

            return $this->persist($user, 'unggah', 'image/png', 'png', $bytes);
        }
        if ($isPng) {
            $rgbBytes = \App\Support\PngNormalizer::toRgbPng(file_get_contents($file->getRealPath()));

            return $this->persist($user, 'unggah', 'image/png', 'png', $rgbBytes);
        }

        return $this->persist($user, 'unggah', 'image/jpeg', 'jpg', file_get_contents($file->getRealPath()));
    }

    private function persist(User $user, string $tipe, string $mime, string $ext, string $bytes): TeacherSignature
    {
        $old = TeacherSignature::where('user_id', $user->id)->first();
        $path = 'signatures/'.$user->id.'-'.bin2hex(random_bytes(6)).'.'.$ext;
        Storage::disk('local')->put($path, $bytes);
        if ($old) {
            Storage::disk('local')->delete($old->file_path);
        }

        return TeacherSignature::updateOrCreate(['user_id' => $user->id], [
            'tipe' => $tipe, 'mime' => $mime, 'file_path' => $path, 'hash' => hash('sha256', $bytes),
        ]);
    }

    public function delete(User $user): void
    {
        if ($sig = TeacherSignature::where('user_id', $user->id)->first()) {
            Storage::disk('local')->delete($sig->file_path);
            $sig->delete();
        }
    }
}
