<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $table = 'classes';

    protected $fillable = ['nama', 'paralel', 'education_program_id', 'tingkat', 'academic_year_id', 'curriculum_id', 'teacher_id', 'status'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(EducationProgram::class, 'education_program_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'class_id');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', 'aktif');
    }

    /** Mata pelajaran aktif untuk kelas ini (program + tingkat + kurikulum). */
    public function subjects()
    {
        return Subject::query()
            ->where('education_program_id', $this->education_program_id)
            ->where('tingkat', $this->tingkat)
            ->where('curriculum_id', $this->curriculum_id)
            ->where('is_active', true)
            ->orderBy('urutan')->orderBy('nama');
    }

    public static function buildName(EducationProgram $p, int $tingkat, AcademicYear $y, string $paralel = ''): string
    {
        return trim("{$p->nama} Kelas {$tingkat}".($paralel !== '' ? " {$paralel}" : '')." Tahun Ajaran {$y->nama}");
    }

    /** Batasi kelas yang boleh diakses pengguna: guru hanya kelas yang ditugaskan padanya. */
    public function scopeForUser(Builder $q, User $user): Builder
    {
        if ($user->hasRole('guru')) {
            return $q->where('teacher_id', $user->teacher?->id ?? 0);
        }
        if ($user->hasRole('siswa')) {
            return $q->whereIn('id', StudentEnrollment::where('student_id', $user->student?->id ?? 0)->pluck('class_id'));
        }

        return $q;
    }
}
