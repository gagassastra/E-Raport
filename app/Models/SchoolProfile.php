<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolProfile extends Model
{
    protected $fillable = [
        'nama_lembaga', 'jenis_lembaga', 'npsn', 'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos',
        'telepon', 'email', 'website', 'logo_path', 'kepala_nama', 'kepala_identitas_label', 'kepala_identitas', 'kepala_user_id',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create(['nama_lembaga' => 'PKBM AL-QUDWAH']);
    }

    public function kepalaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_user_id');
    }

    public function getAlamatLengkapAttribute(): string
    {
        $parts = array_filter([
            $this->alamat,
            $this->desa ? 'Desa/Kel. '.$this->desa : null,
            $this->kecamatan ? 'Kec. '.$this->kecamatan : null,
            $this->kabupaten,
            $this->provinsi,
            $this->kode_pos,
        ]);

        return implode(', ', $parts);
    }

    public static function defaultLogoFile(): ?string
    {
        foreach ([
            base_path('Image/logo.png'),
            base_path('image/logo.png'),
            public_path('image/logo.png'),
            public_path('Image/logo.png'),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function hasLogo(): bool
    {
        if ($this->logo_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->logo_path)) {
            return true;
        }

        return static::defaultLogoFile() !== null;
    }
}
