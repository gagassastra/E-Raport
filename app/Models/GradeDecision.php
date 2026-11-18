<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeDecision extends Model
{
    public const KEPUTUSAN = ['naik' => 'Naik Kelas', 'tinggal' => 'Tidak Naik Kelas', 'lulus' => 'Lulus'];

    protected $fillable = [
        'student_id', 'enrollment_id', 'from_class_id', 'to_class_id', 'academic_year_id', 'keputusan', 'status',
        'catatan', 'diputuskan_oleh', 'dikonfirmasi_oleh', 'dikonfirmasi_at',
    ];

    protected function casts(): array
    {
        return ['dikonfirmasi_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'from_class_id');
    }

    public function toClass(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'to_class_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function pengonfirmasi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh');
    }
}
