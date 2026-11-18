<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AssessmentScore;
use App\Models\Curriculum;
use App\Models\EducationProgram;
use App\Models\Subject;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $programs = EducationProgram::orderBy('tingkat_awal')->get();
        $curricula = Curriculum::orderBy('nama')->get();
        $editing = $request->filled('edit') ? Subject::find($request->query('edit')) : null;

        $q = Subject::with('program', 'curriculum')
            ->when($request->filled('program_id'), fn ($x) => $x->where('education_program_id', $request->query('program_id')))
            ->when($request->filled('tingkat'), fn ($x) => $x->where('tingkat', $request->query('tingkat')))
            ->when($request->filled('curriculum_id'), fn ($x) => $x->where('curriculum_id', $request->query('curriculum_id')))
            ->when($request->filled('q'), fn ($x) => $x->where(fn ($w) => $w->where('nama', 'like', '%'.$request->query('q').'%')->orWhere('kode', 'like', '%'.$request->query('q').'%')));

        $subjects = $q->orderBy('education_program_id')->orderBy('tingkat')->orderBy('urutan')->orderBy('nama')->paginate(25)->withQueryString();

        return view('master.subjects', compact('subjects', 'programs', 'curricula', 'editing'));
    }

    private function validatedData(Request $request, ?Subject $ignore = null): array
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:150'],
            'education_program_id' => ['required', 'exists:education_programs,id'],
            'tingkat' => ['required', 'integer'],
            'curriculum_id' => ['required', 'exists:curricula,id'],
            'urutan' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $program = EducationProgram::findOrFail((int) $data['education_program_id']);
        if (! $program->isTingkatValid((int) $data['tingkat'])) {
            throw ValidationException::withMessages([
                'tingkat' => "Tingkat kelas untuk {$program->nama} hanya boleh ".implode(', ', $program->tingkatList()).'.',
            ]);
        }

        $dup = Subject::where('kode', $data['kode'])
            ->where('education_program_id', $data['education_program_id'])
            ->where('tingkat', $data['tingkat'])
            ->where('curriculum_id', $data['curriculum_id'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();
        if ($dup) {
            throw ValidationException::withMessages(['kode' => 'Kode mata pelajaran tersebut sudah terdaftar untuk program, tingkat, dan kurikulum ini.']);
        }

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $sub = Subject::create($data);
        ActivityLogger::log('tambah', 'mapel', "Menambahkan mata pelajaran {$sub->nama} (Kelas {$sub->tingkat}).", $sub);

        return redirect()->route('master.subjects.index', ['program_id' => $sub->education_program_id, 'tingkat' => $sub->tingkat])->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $this->validatedData($request, $subject);
        $subject->update($data);
        ActivityLogger::log('ubah', 'mapel', "Memperbarui mata pelajaran {$subject->nama}.", $subject);

        return redirect()->route('master.subjects.index', ['program_id' => $subject->education_program_id, 'tingkat' => $subject->tingkat])->with('success', 'Mata pelajaran diperbarui.');
    }

    public function destroy(Subject $subject)
    {
        if (AssessmentScore::where('subject_id', $subject->id)->exists()) {
            return back()->with('error', 'Mata pelajaran tidak dapat dihapus karena sudah memiliki data nilai. Nonaktifkan jika tidak digunakan lagi.');
        }
        $nama = $subject->nama;
        $subject->delete();
        ActivityLogger::log('hapus', 'mapel', "Menghapus mata pelajaran {$nama}.");

        return back()->with('success', 'Mata pelajaran dihapus.');
    }
}
