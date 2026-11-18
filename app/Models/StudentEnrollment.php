<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    public const STATUS = ['aktif' => 'Aktif', 'naik' => 'Naik Kelas', 'tinggal' => 'Tidak Naik', 'lulus' => 'Lulus', 'pindah' => 'Pindah Kelas', 'keluar' => 'Keluar'];

    protected $fillable = ['student_id', 'class_id', 'academic_year_id', 'status', 'tanggal_masuk', 'tanggal_keluar', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal_masuk' => 'date', 'tanggal_keluar' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
