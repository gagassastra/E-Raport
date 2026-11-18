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
}
