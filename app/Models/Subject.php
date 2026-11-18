<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subject extends Model
{
    protected $fillable = ['kode', 'nama', 'education_program_id', 'tingkat', 'curriculum_id', 'urutan', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(EducationProgram::class, 'education_program_id');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }
}
