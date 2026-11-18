<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationProgram extends Model
{
    protected $fillable = ['kode', 'nama', 'setara', 'tingkat_awal', 'tingkat_akhir', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Daftar tingkat kelas yang sah untuk program ini (Paket B: 7-9, Paket C: 10-12). */
    public function tingkatList(): array
    {
        return range($this->tingkat_awal, $this->tingkat_akhir);
    }

    public function isTingkatValid(int $tingkat): bool
    {
        return $tingkat >= $this->tingkat_awal && $tingkat <= $this->tingkat_akhir;
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
