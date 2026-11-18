<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = ['student_id', 'class_id', 'academic_year_id', 'semester_id', 'sakit', 'izin', 'alpa', 'updated_by'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
