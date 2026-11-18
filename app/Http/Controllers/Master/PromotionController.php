<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeDecision;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $years = AcademicYear::orderByDesc('nama')->get();
        $fromYear = $years->firstWhere('id', (int) $request->query('from_year_id')) ?? AcademicYear::active() ?? $years->first();
        $fromClasses = $fromYear
            ? Classroom::where('academic_year_id', $fromYear->id)->with('program')->orderBy('education_program_id')->orderBy('tingkat')->orderBy('paralel')->get()
            : collect();
        $fromClass = $fromClasses->firstWhere('id', (int) $request->query('from_class_id')) ?? $fromClasses->first();

        $enrollments = collect();
        $existingDecisions = collect();
        $targetClassesNaik = collect();
        $targetClassesTinggal = collect();
        $sameYearClasses = collect();
        $isTerminalGrade = false;

        if ($fromClass) {
            $enrollments = StudentEnrollment::with('student')
                ->where('class_id', $fromClass->id)
                ->get()
                ->sortBy(fn ($e) => $e->student->nama)
                ->values();

            $existingDecisions = GradeDecision::where('from_class_id', $fromClass->id)->get()->keyBy('enrollment_id');
            $isTerminalGrade = (int) $fromClass->tingkat === (int) $fromClass->program->tingkat_akhir;

            if (! $isTerminalGrade) {
                $targetClassesNaik = Classroom::with('academicYear')
                    ->where('education_program_id', $fromClass->education_program_id)
                    ->where('tingkat', $fromClass->tingkat + 1)
                    ->where('academic_year_id', '!=', $fromClass->academic_year_id)
                    ->orderByDesc('academic_year_id')
                    ->get();
            }

            $targetClassesTinggal = Classroom::with('academicYear')
                ->where('education_program_id', $fromClass->education_program_id)
                ->where('tingkat', $fromClass->tingkat)
                ->where('academic_year_id', '!=', $fromClass->academic_year_id)
                ->orderByDesc('academic_year_id')
                ->get();

            $sameYearClasses = Classroom::where('academic_year_id', $fromClass->academic_year_id)
                ->where('education_program_id', $fromClass->education_program_id)
                ->where('id', '!=', $fromClass->id)
                ->orderBy('tingkat')
                ->get();
        }

        $history = GradeDecision::with(['student', 'fromClass.academicYear', 'toClass.academicYear', 'pembuat', 'pengonfirmasi'])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'riwayat_page')->withQueryString();

        return view('master.promotions', compact(
            'years', 'fromYear', 'fromClasses', 'fromClass', 'enrollments', 'existingDecisions',
            'targetClassesNaik', 'targetClassesTinggal', 'sameYearClasses', 'isTerminalGrade', 'history'
        ));
    }

    /** Proses massal kenaikan kelas / tinggal kelas / kelulusan (Draft atau Konfirmasi & Terapkan). */
    public function process(Request $request)
    {
        $fromClass = Classroom::with('program', 'academicYear')->findOrFail((int) $request->input('from_class_id'));
        $aksi = $request->input('aksi', 'draft'); // draft | konfirmasi
        $isTerminal = (int) $fromClass->tingkat === (int) $fromClass->program->tingkat_akhir;

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.keputusan' => ['nullable', 'in:naik,tinggal,lulus'],
            'items.*.to_class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'items.*.catatan' => ['nullable', 'string', 'max:250'],
        ]);

        $count = 0;
        DB::transaction(function () use ($request, $fromClass, $aksi, $isTerminal, &$count) {
            foreach ($request->input('items') as $enrollmentId => $row) {
                $keputusan = $row['keputusan'] ?? null;
                if (! $keputusan) {
                    continue;
                }
                $enrollment = StudentEnrollment::with('student')->where('id', (int) $enrollmentId)->where('class_id', $fromClass->id)->first();
                if (! $enrollment) {
                    continue;
                }

                $toClass = null;
                if ($keputusan === 'lulus') {
                    if (! $isTerminal) {
                        throw ValidationException::withMessages(['keputusan' => "Kelulusan hanya untuk tingkat akhir ({$fromClass->program->nama} Kelas {$fromClass->program->tingkat_akhir})."]);
                    }
                } elseif ($keputusan === 'naik') {
                    if ($isTerminal) {
                        throw ValidationException::withMessages(['keputusan' => "Kelas {$fromClass->tingkat} adalah tingkat akhir {$fromClass->program->nama}; gunakan keputusan Lulus atau Tidak Naik."]);
                    }
                    $toClassId = (int) ($row['to_class_id'] ?? $request->input('default_to_class_naik'));
                    $toClass = Classroom::find($toClassId);
                    if (! $toClass || (int) $toClass->education_program_id !== (int) $fromClass->education_program_id || (int) $toClass->tingkat !== (int) $fromClass->tingkat + 1 || (int) $toClass->academic_year_id === (int) $fromClass->academic_year_id) {
                        throw ValidationException::withMessages(['to_class_id' => "Pilih kelas tujuan Kelas ".($fromClass->tingkat + 1)." ({$fromClass->program->nama}) pada tahun ajaran berikutnya untuk {$enrollment->student->nama}."]);
                    }
                } elseif ($keputusan === 'tinggal') {
                    $toClassId = (int) ($row['to_class_id'] ?? $request->input('default_to_class_tinggal'));
                    $toClass = Classroom::find($toClassId);
                    if (! $toClass || (int) $toClass->education_program_id !== (int) $fromClass->education_program_id || (int) $toClass->tingkat !== (int) $fromClass->tingkat || (int) $toClass->academic_year_id === (int) $fromClass->academic_year_id) {
                        throw ValidationException::withMessages(['to_class_id' => "Pilih kelas tujuan Kelas {$fromClass->tingkat} ({$fromClass->program->nama}) pada tahun ajaran berikutnya untuk {$enrollment->student->nama}."]);
                    }
                }

                $decision = GradeDecision::updateOrCreate(
                    ['enrollment_id' => $enrollment->id, 'from_class_id' => $fromClass->id],
                    [
                        'student_id' => $enrollment->student_id,
                        'to_class_id' => $toClass?->id,
                        'academic_year_id' => $fromClass->academic_year_id,
                        'keputusan' => $keputusan,
                        'status' => $aksi === 'konfirmasi' ? 'dikonfirmasi' : 'draft',
                        'catatan' => $row['catatan'] ?? null,
                        'diputuskan_oleh' => $request->user()->id,
                        'dikonfirmasi_oleh' => $aksi === 'konfirmasi' ? $request->user()->id : null,
                        'dikonfirmasi_at' => $aksi === 'konfirmasi' ? now() : null,
                    ]
                );

                if ($aksi === 'konfirmasi') {
                    // Simpan riwayat kelas lama tanpa menimpa/menghapus
                    $enrollment->update([
                        'status' => $keputusan,
                        'tanggal_keluar' => now()->toDateString(),
                        'catatan' => $row['catatan'] ?? ('Keputusan: '.(GradeDecision::KEPUTUSAN[$keputusan] ?? $keputusan)),
                    ]);

                    if ($keputusan === 'lulus') {
                        $enrollment->student->update(['status' => 'lulus']);
                    } elseif ($toClass) {
                        StudentEnrollment::updateOrCreate(
                            ['student_id' => $enrollment->student_id, 'class_id' => $toClass->id],
                            [
                                'academic_year_id' => $toClass->academic_year_id,
                                'status' => 'aktif',
                                'tanggal_masuk' => now()->toDateString(),
                                'catatan' => ($keputusan === 'naik' ? 'Naik dari ' : 'Mengulang dari ').$fromClass->nama,
                            ]
                        );
                    }
                }
                $count++;
            }
        });

        ActivityLogger::log(
            $aksi === 'konfirmasi' ? 'konfirmasi_kenaikan' : 'draft_kenaikan',
            'kenaikan_kelas',
            ($aksi === 'konfirmasi' ? 'Mengonfirmasi & menerapkan' : 'Menyimpan draft')." keputusan kenaikan/kelulusan {$fromClass->nama} ({$count} peserta didik).",
            $fromClass
        );

        return back()->with('success', $aksi === 'konfirmasi'
            ? "{$count} keputusan kenaikan kelas / kelulusan telah dikonfirmasi dan diterapkan. Riwayat kelas sebelumnya tetap tersimpan."
            : "{$count} keputusan disimpan sebagai draft.");
    }

    /** Pemindahan kelas pada tahun ajaran dan program yang sama (data riwayat tetap tercatat). */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'to_class_id' => ['required', 'exists:classes,id'],
            'catatan' => ['nullable', 'string', 'max:250'],
        ]);

        $enrollment = StudentEnrollment::with('student', 'classroom.program')->findOrFail((int) $data['enrollment_id']);
        $fromClass = $enrollment->classroom;
        $toClass = Classroom::findOrFail((int) $data['to_class_id']);

        if ((int) $toClass->education_program_id !== (int) $fromClass->education_program_id) {
            throw ValidationException::withMessages(['to_class_id' => 'Tidak dapat memindahkan peserta didik lintas program pendidikan (Paket B dan Paket C tidak boleh tercampur).']);
        }
        if ((int) $toClass->academic_year_id !== (int) $fromClass->academic_year_id) {
            throw ValidationException::withMessages(['to_class_id' => 'Untuk lintas tahun ajaran, gunakan formulir Kenaikan Kelas di atas.']);
        }

        DB::transaction(function () use ($enrollment, $fromClass, $toClass, $data, $request) {
            $enrollment->update([
                'status' => 'pindah',
                'tanggal_keluar' => now()->toDateString(),
                'catatan' => $data['catatan'] ?: 'Pindah ke '.$toClass->nama,
            ]);
            StudentEnrollment::updateOrCreate(
                ['student_id' => $enrollment->student_id, 'class_id' => $toClass->id],
                [
                    'academic_year_id' => $toClass->academic_year_id,
                    'status' => 'aktif',
                    'tanggal_masuk' => now()->toDateString(),
                    'catatan' => 'Pindahan dari '.$fromClass->nama,
                ]
            );
            GradeDecision::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'from_class_id' => $fromClass->id,
                'to_class_id' => $toClass->id,
                'academic_year_id' => $fromClass->academic_year_id,
                'keputusan' => 'pindah',
                'status' => 'dikonfirmasi',
                'catatan' => $data['catatan'] ?: 'Pemindahan kelas',
                'diputuskan_oleh' => $request->user()->id,
                'dikonfirmasi_oleh' => $request->user()->id,
                'dikonfirmasi_at' => now(),
            ]);
        });

        ActivityLogger::log('pindah_kelas', 'kenaikan_kelas', "Memindahkan {$enrollment->student->nama} dari {$fromClass->nama} ke {$toClass->nama}.", $enrollment->student);

        return back()->with('success', "{$enrollment->student->nama} berhasil dipindahkan ke {$toClass->nama}.");
    }
}
