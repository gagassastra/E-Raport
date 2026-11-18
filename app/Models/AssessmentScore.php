<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    protected $fillable = [
        'student_id', 'class_id', 'subject_id', 'education_program_id', 'curriculum_id', 'academic_year_id', 'semester_id',
        'sumatif', 'asts', 'asas', 'nilai_akhir', 'deskripsi', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['sumatif' => 'float', 'asts' => 'float', 'asas' => 'float'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function isComplete(): bool
    {
        return $this->sumatif !== null && $this->asts !== null && $this->asas !== null;
    }
}
