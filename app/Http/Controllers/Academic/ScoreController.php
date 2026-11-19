<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Concerns\ResolvesClassContext;
use App\Http\Controllers\Controller;
use App\Models\AssessmentScore;
use App\Models\GradeWeight;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Services\ReportCardService;
use App\Services\ScoreCalculator;
use App\Support\SimpleXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreController extends Controller
{
    use ResolvesClassContext;

    public function __construct(private ReportCardService $svc) {}

    public function index(Request $request)
    {
        [$semesters, $semester, $classes, $class] = $this->classContext($request);
        $subjects = $class ? $class->subjects()->get() : collect();
        $subject = $subjects->firstWhere('id', (int) $request->query('mapel_id')) ?? $subjects->first();
        $weight = GradeWeight::forSemester($semester?->id);

        $rows = collect();
        $locked = collect();
        if ($class && $semester && $subject) {
            $this->svc->sync($class, $semester);
            $ids = $this->svc->studentIds($class);
            $students = \App\Models\Student::whereIn('id', $ids)->orderBy('nama')->get();
            $scores = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)->where('subject_id', $subject->id)->get()->keyBy('student_id');
            $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
            $rows = $students->map(fn ($s) => (object) ['student' => $s, 'score' => $scores->get($s->id), 'locked' => $locked->has($s->id)]);
        }
        $readonly = ! $semester || $semester->academicYear->is_archived;

        return view('academic.scores', compact('semesters', 'semester', 'classes', 'class', 'subjects', 'subject', 'rows', 'weight', 'readonly'));
    }

    public function store(Request $request)
    {
        [$class, $semester] = $this->resolveEditableClass($request);
        $subject = Subject::findOrFail((int) $request->input('mapel_id'));
        abort_unless($class->subjects()->where('id', $subject->id)->exists(), 422, 'Mata pelajaran bukan bagian dari kelas ini.');

        $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.sumatif' => ['nullable', 'numeric', 'between:0,100'],
            'scores.*.asts' => ['nullable', 'numeric', 'between:0,100'],
            'scores.*.asas' => ['nullable', 'numeric', 'between:0,100'],
            'scores.*.deskripsi' => ['nullable', 'string', 'max:2000'],
        ], [
            'scores.*.sumatif.between' => 'Nilai Sumatif harus berada pada rentang 0–100.',
            'scores.*.asts.between' => 'Nilai ASTS harus berada pada rentang 0–100.',
            'scores.*.asas.between' => 'Nilai ASAS harus berada pada rentang 0–100.',
            '*.numeric' => 'Nilai harus berupa angka.',
        ]);

        $weight = GradeWeight::forSemester($semester->id);
        $validStudents = $this->svc->studentIds($class)->flip();
        $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
        $saved = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $class, $semester, $subject, $weight, $validStudents, $locked, &$saved, &$skipped) {
            foreach ($request->input('scores') as $studentId => $in) {
                $studentId = (int) $studentId;
                if (! isset($validStudents[$studentId])) {
                    continue;
                }
                if ($locked->has($studentId)) {
                    $skipped++;

                    continue;
                }
                $s = ScoreCalculator::parse($in['sumatif'] ?? null);
                $a = ScoreCalculator::parse($in['asts'] ?? null);
                $b = ScoreCalculator::parse($in['asas'] ?? null);
                $desk = isset($in['deskripsi']) ? trim($in['deskripsi']) : null;
                $existing = AssessmentScore::where(['student_id' => $studentId, 'class_id' => $class->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id])->first();
                if (! $existing && $s === null && $a === null && $b === null && ! filled($desk)) {
                    continue;
                }
                AssessmentScore::updateOrCreate(
                    ['student_id' => $studentId, 'class_id' => $class->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id],
                    [
                        'education_program_id' => $class->education_program_id, 'curriculum_id' => $class->curriculum_id, 'academic_year_id' => $class->academic_year_id,
                        'sumatif' => $s, 'asts' => $a, 'asas' => $b, 'nilai_akhir' => ScoreCalculator::nilaiAkhir($s, $a, $b, $weight),
                        'deskripsi' => $desk ?: null, 'updated_by' => $request->user()->id,
                    ]
                );
                $saved++;
            }
        });
        $this->svc->sync($class, $semester);
        ActivityLogger::log('simpan_nilai', 'nilai', "Menyimpan nilai {$subject->nama} kelas {$class->nama} ({$semester->label}): $saved data.", $class, ['mapel' => $subject->nama, 'disimpan' => $saved, 'dilewati_terkunci' => $skipped]);

        $msg = "Nilai {$subject->nama} tersimpan ($saved peserta didik).";
        $resp = back()->with('success', $msg);

        return $skipped ? $resp->with('warning', "$skipped peserta didik dilewati karena rapornya sedang diajukan/disahkan (terkunci).") : $resp;
    }

    /** Ekspor daftar & nilai peserta didik mata pelajaran saat ini ke berkas Microsoft Excel (.xlsx). */
    public function export(Request $request)
    {
        [, $semester, , $class] = $this->classContext($request);
        abort_unless($class && $semester, 404, 'Kelas atau semester tidak ditemukan.');
        $subjects = $class->subjects()->get();
        $subject = $subjects->firstWhere('id', (int) $request->query('mapel_id')) ?? $subjects->first();
        abort_unless($subject, 404, 'Mata pelajaran tidak ditemukan pada kelas ini.');

        $weight = GradeWeight::forSemester($semester->id);
        $ids = $this->svc->studentIds($class);
        $students = Student::whereIn('id', $ids)->orderBy('nama')->get();
        $scores = AssessmentScore::where('class_id', $class->id)->where('semester_id', $semester->id)->where('subject_id', $subject->id)->get()->keyBy('student_id');

        $excelRows = [
            [['v' => 'DAFTAR NILAI PESERTA DIDIK — PKBM AL-QUDWAH', 's' => 1]],
            [['v' => 'Kelas: '.$class->nama.' ('.$class->program->nama.') | Mata Pelajaran: '.$subject->nama, 's' => 2]],
            [['v' => 'Tahun Ajaran: '.$semester->academicYear->nama.' — Semester '.ucfirst($semester->jenis).' | Petunjuk: Isi nilai Sumatif, ASTS, dan ASAS (0–100). Kosongkan Deskripsi bila ingin diisi rekomendasi otomatis saat impor.', 's' => 0]],
            [],
            [
                ['v' => 'No', 's' => 3],
                ['v' => 'NIS', 's' => 3],
                ['v' => 'Nama Peserta Didik', 's' => 3],
                ['v' => 'Sumatif', 's' => 3],
                ['v' => 'ASTS', 's' => 3],
                ['v' => 'ASAS', 's' => 3],
                ['v' => 'Nilai Akhir', 's' => 3],
                ['v' => 'Deskripsi Capaian Pembelajaran', 's' => 3],
            ],
        ];

        foreach ($students as $i => $student) {
            $sc = $scores->get($student->id);
            $rNum = $i + 6;
            if ($weight->mode === 'bobot') {
                $ws = (float) $weight->bobot_sumatif;
                $wa = (float) $weight->bobot_asts;
                $wb = (float) $weight->bobot_asas;
                $formula = "IF(COUNT(D{$rNum}:F{$rNum})=3,ROUND((D{$rNum}*{$ws}+E{$rNum}*{$wa}+F{$rNum}*{$wb})/100,0),\"\")";
            } else {
                $formula = "IF(COUNT(D{$rNum}:F{$rNum})=3,ROUND(AVERAGE(D{$rNum}:F{$rNum}),0),\"\")";
            }

            $excelRows[] = [
                ['v' => $i + 1, 's' => 5, 't' => 'n'],
                ['v' => (string) $student->nis, 's' => 5, 't' => 's'],
                ['v' => (string) $student->nama, 's' => 4, 't' => 's'],
                ['v' => $sc?->sumatif !== null ? (float) $sc->sumatif : '', 's' => 5],
                ['v' => $sc?->asts !== null ? (float) $sc->asts : '', 's' => 5],
                ['v' => $sc?->asas !== null ? (float) $sc->asas : '', 's' => 5],
                ['v' => $sc?->nilai_akhir ?? '', 's' => 6, 'f' => $formula],
                ['v' => (string) ($sc?->deskripsi ?? ''), 's' => 4, 't' => 's'],
            ];
        }

        $bytes = SimpleXlsx::write('Input Nilai', $excelRows, [
            1 => 6,
            2 => 16,
            3 => 32,
            4 => 13,
            5 => 13,
            6 => 13,
            7 => 14,
            8 => 58,
        ]);

        $fileName = 'Nilai_'.preg_replace('/[^A-Za-z0-9]+/', '_', $subject->nama.'_'.$class->nama.'_'.ucfirst($semester->jenis)).'.xlsx';
        ActivityLogger::log('ekspor_nilai', 'nilai', "Mengekspor Excel nilai {$subject->nama} kelas {$class->nama} ({$semester->label}).", $class);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Impor nilai peserta didik dari berkas Microsoft Excel (.xlsx) atau CSV. */
    public function import(Request $request)
    {
        [$class, $semester] = $this->resolveEditableClass($request);
        $subject = Subject::findOrFail((int) $request->input('mapel_id'));
        abort_unless($class->subjects()->where('id', $subject->id)->exists(), 422, 'Mata pelajaran bukan bagian dari kelas ini.');

        $request->validate([
            'file_excel' => ['required', 'file', 'max:4096'],
        ], [
            'file_excel.required' => 'Pilih berkas Excel (.xlsx) atau CSV terlebih dahulu.',
        ]);

        $file = $request->file('file_excel');
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            throw ValidationException::withMessages(['file_excel' => 'Format berkas harus berupa Microsoft Excel (.xlsx) atau CSV (.csv).']);
        }

        try {
            $rawRows = SimpleXlsx::read($file->getRealPath(), $ext);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file_excel' => 'Berkas tidak dapat dibaca: '.$e->getMessage()]);
        }

        if (! $rawRows) {
            throw ValidationException::withMessages(['file_excel' => 'Berkas Excel kosong atau tidak memiliki data.']);
        }

        // Deteksi baris header secara otomatis
        $colMap = ['nis' => 1, 'nama' => 2, 'sumatif' => 3, 'asts' => 4, 'asas' => 5, 'deskripsi' => 7];
        $startRow = 0;
        foreach ($rawRows as $rIdx => $row) {
            $lower = array_map(fn ($v) => strtolower(trim((string) $v)), $row);
            $foundNisOrNama = false;
            $foundScoreCol = false;
            $candidate = [];
            foreach ($lower as $cIdx => $h) {
                if (in_array($h, ['nis', 'nomor induk', 'no induk', 'nis/nisn'], true)) {
                    $candidate['nis'] = $cIdx;
                    $foundNisOrNama = true;
                } elseif (in_array($h, ['nama', 'nama peserta didik', 'nama siswa', 'nama lengkap'], true)) {
                    $candidate['nama'] = $cIdx;
                    $foundNisOrNama = true;
                } elseif (str_contains($h, 'sumatif')) {
                    $candidate['sumatif'] = $cIdx;
                    $foundScoreCol = true;
                } elseif (in_array($h, ['asts', 'nilai asts', 'pts'], true)) {
                    $candidate['asts'] = $cIdx;
                    $foundScoreCol = true;
                } elseif (in_array($h, ['asas', 'nilai asas', 'pas', 'uas'], true)) {
                    $candidate['asas'] = $cIdx;
                    $foundScoreCol = true;
                } elseif (str_contains($h, 'deskripsi') || str_contains($h, 'capaian')) {
                    $candidate['deskripsi'] = $cIdx;
                }
            }
            if ($foundNisOrNama && $foundScoreCol) {
                $colMap = array_merge($colMap, $candidate);
                $startRow = $rIdx + 1;
                break;
            }
        }

        $ids = $this->svc->studentIds($class);
        $students = Student::whereIn('id', $ids)->get();
        $byNis = [];
        $byNama = [];
        foreach ($students as $st) {
            $byNis[strtolower(trim((string) $st->nis))] = $st;
            $byNama[strtolower(trim((string) $st->nama))] = $st;
        }

        $weight = GradeWeight::forSemester($semester->id);
        $locked = ReportCard::where('class_id', $class->id)->where('semester_id', $semester->id)->whereIn('status', ['menunggu_pengesahan', 'disahkan'])->pluck('status', 'student_id');
        $autoDeskripsi = $request->boolean('auto_deskripsi', true);

        $saved = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rawRows, $startRow, $colMap, $byNis, $byNama, $class, $semester, $subject, $weight, $locked, $autoDeskripsi, $request, &$saved, &$skipped, &$errors) {
            for ($i = $startRow, $total = count($rawRows); $i < $total; $i++) {
                $row = $rawRows[$i];
                $nisRaw = strtolower(trim((string) ($row[$colMap['nis']] ?? '')));
                $namaRaw = strtolower(trim((string) ($row[$colMap['nama']] ?? '')));
                if ($nisRaw === '' && $namaRaw === '') {
                    continue;
                }

                $student = ($nisRaw !== '' ? ($byNis[$nisRaw] ?? null) : null) ?? ($namaRaw !== '' ? ($byNama[$namaRaw] ?? null) : null);
                if (! $student) {
                    continue;
                }
                if ($locked->has($student->id)) {
                    $skipped++;

                    continue;
                }

                $rawS = trim((string) ($row[$colMap['sumatif']] ?? ''));
                $rawA = trim((string) ($row[$colMap['asts']] ?? ''));
                $rawB = trim((string) ($row[$colMap['asas']] ?? ''));
                $desk = isset($colMap['deskripsi']) ? trim((string) ($row[$colMap['deskripsi']] ?? '')) : '';

                foreach (['Sumatif' => $rawS, 'ASTS' => $rawA, 'ASAS' => $rawB] as $lbl => $val) {
                    if ($val !== '') {
                        $norm = str_replace(',', '.', $val);
                        if (! is_numeric($norm) || (float) $norm < 0 || (float) $norm > 100) {
                            $errors[] = "{$student->nama}: Nilai {$lbl} ({$val}) harus berupa angka 0–100.";
                        }
                    }
                }
                if ($errors) {
                    continue;
                }

                $s = ScoreCalculator::parse($rawS);
                $a = ScoreCalculator::parse($rawA);
                $b = ScoreCalculator::parse($rawB);
                $nilaiAkhir = ScoreCalculator::nilaiAkhir($s, $a, $b, $weight);

                $existing = AssessmentScore::where(['student_id' => $student->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id])->first();
                if (! $existing && $s === null && $a === null && $b === null && $desk === '') {
                    continue;
                }

                if ($desk === '') {
                    if ($autoDeskripsi && $nilaiAkhir !== null) {
                        $desk = (string) ScoreCalculator::rekomendasi($subject->nama, $nilaiAkhir);
                    } elseif ($existing && filled($existing->deskripsi)) {
                        $desk = (string) $existing->deskripsi;
                    }
                }

                AssessmentScore::updateOrCreate(
                    ['student_id' => $student->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'semester_id' => $semester->id],
                    [
                        'education_program_id' => $class->education_program_id,
                        'curriculum_id' => $class->curriculum_id,
                        'academic_year_id' => $class->academic_year_id,
                        'sumatif' => $s,
                        'asts' => $a,
                        'asas' => $b,
                        'nilai_akhir' => $nilaiAkhir,
                        'deskripsi' => $desk !== '' ? $desk : null,
                        'updated_by' => $request->user()->id,
                    ]
                );
                $saved++;
            }

            if ($errors) {
                throw ValidationException::withMessages(['file_excel' => implode(' | ', array_slice($errors, 0, 3))]);
            }
        });

        if ($saved === 0 && $skipped === 0) {
            throw ValidationException::withMessages(['file_excel' => 'Tidak ada baris nilai peserta didik yang cocok dengan kelas ini atau seluruh kolom nilai pada berkas masih kosong.']);
        }

        $this->svc->sync($class, $semester);
        ActivityLogger::log('impor_nilai', 'nilai', "Mengimpor Excel nilai {$subject->nama} kelas {$class->nama} ({$semester->label}): {$saved} data.", $class, ['mapel' => $subject->nama, 'disimpan' => $saved, 'dilewati_terkunci' => $skipped]);

        $resp = back()->with('success', "Impor Excel nilai {$subject->nama} berhasil ({$saved} peserta didik diperbarui).");

        return $skipped ? $resp->with('warning', "{$skipped} peserta didik dilewati karena rapornya sedang diajukan/disahkan (terkunci).") : $resp;
    }
}
