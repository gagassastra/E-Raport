<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    public const STATUS = ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'keluar' => 'Keluar', 'nonaktif' => 'Nonaktif'];

    protected $fillable = [
        'user_id', 'nis', 'nisn', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'alamat',
        'nama_wali', 'telepon', 'education_program_id', 'status', 'tahun_masuk',
    ];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(EducationProgram::class, 'education_program_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function currentEnrollment()
    {
        return $this->enrollments()->where('status', 'aktif')->latest('id')->first();
    }

    public function scopeSearch($q, ?string $term)
    {
        if ($term) {
            $q->where(fn ($w) => $w->where('nama', 'like', "%$term%")->orWhere('nis', 'like', "%$term%")->orWhere('nisn', 'like', "%$term%"));
        }

        return $q;
    }
}
