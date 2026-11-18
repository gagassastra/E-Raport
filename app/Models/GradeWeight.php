<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeWeight extends Model
{
    protected $fillable = ['semester_id', 'mode', 'bobot_sumatif', 'bobot_asts', 'bobot_asas', 'updated_by'];

    protected function casts(): array
    {
        return ['bobot_sumatif' => 'float', 'bobot_asts' => 'float', 'bobot_asas' => 'float'];
    }

    /** Bobot yang berlaku untuk sebuah semester: override semester, jika tidak ada pakai bobot bawaan. */
    public static function forSemester(?int $semesterId): self
    {
        $w = $semesterId ? static::where('semester_id', $semesterId)->first() : null;

        return $w ?? static::whereNull('semester_id')->first()
            ?? new static(['mode' => 'rata', 'bobot_sumatif' => 33.34, 'bobot_asts' => 33.33, 'bobot_asas' => 33.33]);
    }
}
