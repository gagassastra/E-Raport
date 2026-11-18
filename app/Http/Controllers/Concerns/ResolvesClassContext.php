<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Classroom;
use App\Models\Semester;
use Illuminate\Http\Request;

trait ResolvesClassContext
{
    /**
     * Menentukan semester dan kelas yang sedang dikerjakan berdasarkan hak akses pengguna.
     * Guru hanya mendapatkan kelas yang ditugaskan kepadanya.
     */
    protected function classContext(Request $request): array
    {
        $user = $request->user();
        $semesters = Semester::with('academicYear')->orderByDesc('academic_year_id')->orderBy('jenis')->get();
        $semester = $semesters->firstWhere('id', (int) $request->query('semester_id', $request->input('semester_id'))) ?? Semester::active() ?? $semesters->first();
        $classes = $semester
            ? Classroom::forUser($user)->where('academic_year_id', $semester->academic_year_id)->with('program')->orderBy('tingkat')->orderBy('paralel')->get()
            : collect();
        $class = $classes->firstWhere('id', (int) $request->query('kelas_id', $request->input('kelas_id'))) ?? $classes->first();

        return [$semesters, $semester, $classes, $class];
    }

    protected function resolveEditableClass(Request $request): array
    {
        $user = $request->user();
        $semester = Semester::with('academicYear')->findOrFail((int) $request->input('semester_id'));
        $class = Classroom::forUser($user)->with('program')->findOrFail((int) $request->input('kelas_id'));
        abort_unless($class->academic_year_id === $semester->academic_year_id, 404);
        abort_if($semester->academicYear->is_archived, 403, 'Tahun ajaran ini sudah diarsipkan dan tidak dapat diubah.');

        return [$class, $semester];
    }
}
