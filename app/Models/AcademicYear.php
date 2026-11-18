<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai', 'is_active', 'is_archived'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date',
            'is_active' => 'boolean', 'is_archived' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class)->orderBy('jenis'); // ganjil lebih dulu daripada genap
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
}
