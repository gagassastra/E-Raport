<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\EducationProgram;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $programs = EducationProgram::orderBy('tingkat_awal')->get();
        $q = Student::with(['program', 'user', 'enrollments' => fn ($e) => $e->where('status', 'aktif')->with('classroom')])
            ->search($request->query('q'))
            ->when($request->filled('program_id'), fn ($x) => $x->where('education_program_id', $request->query('program_id')))
            ->when($request->filled('status'), fn ($x) => $x->where('status', $request->query('status')))
            ->when($request->filled('tahun_masuk'), fn ($x) => $x->where('tahun_masuk', $request->query('tahun_masuk')));

        $students = $q->orderBy('nama')->paginate(20)->withQueryString();

        return view('master.students', compact('students', 'programs'));
    }

    public function create()
    {
        $programs = EducationProgram::orderBy('tingkat_awal')->get();
        $student = new Student(['jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif', 'tahun_masuk' => (int) date('Y')]);

        return view('master.student-form', compact('student', 'programs'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('students.manage'), 403);
        $data = $request->validate([
            'nis' => ['required', 'string', 'max:30', 'unique:students,nis'],
            'nisn' => ['nullable', 'string', 'max:20', 'unique:students,nisn'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'nama_wali' => ['nullable', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'education_program_id' => ['required', 'exists:education_programs,id'],
            'status' => ['required', 'in:'.implode(',', array_keys(Student::STATUS))],
            'tahun_masuk' => ['nullable', 'integer', 'between:2000,2100'],
            'username' => ['nullable', 'string', 'max:60', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $username = ($data['username'] ?? null) ?: $data['nis'];
        if (User::where('username', $username)->exists()) {
            throw ValidationException::withMessages(['username' => 'Nama pengguna sudah terpakai.']);
        }
        $roleSiswa = Role::where('slug', 'siswa')->firstOrFail();

        $student = DB::transaction(function () use ($data, $username, $roleSiswa) {
            $user = User::create([
                'name' => $data['nama'],
                'username' => $username,
                'phone' => ($data['telepon'] ?? null) ?: null,
                'password' => Hash::make(($data['password'] ?? null) ?: $data['nis']),
                'role_id' => $roleSiswa->id,
                'is_active' => $data['status'] === 'aktif',
            ]);
            unset($data['username'], $data['password']);
            $data['user_id'] = $user->id;
            $data['nisn'] = ($data['nisn'] ?? null) ?: null;

            return Student::create($data);
        });

        ActivityLogger::log('tambah', 'siswa', "Menambahkan peserta didik {$student->nama} (NIS {$student->nis}).", $student);

        return redirect()->route('master.students.show', $student)->with('success', "Peserta didik {$student->nama} berhasil ditambahkan (akun login: {$username}).");
    }

    public function show(Student $student)
    {
        $student->load([
            'program', 'user',
            'enrollments' => fn ($q) => $q->with('classroom.program', 'academicYear')->orderByDesc('academic_year_id'),
            'reportCards' => fn ($q) => $q->with('classroom.program', 'semester.academicYear')->orderByDesc('academic_year_id')->orderByDesc('semester_id'),
        ]);

        return view('master.student-show', compact('student'));
    }

    public function edit(Student $student)
    {
        $student->load('user');
        $programs = EducationProgram::orderBy('tingkat_awal')->get();

        return view('master.student-form', compact('student', 'programs'));
    }

    public function update(Request $request, Student $student)
    {
        abort_unless($request->user()->hasPermission('students.manage'), 403);
        $userId = $student->user_id;

        $data = $request->validate([
            'nis' => ['required', 'string', 'max:30', 'unique:students,nis,'.$student->id],
            'nisn' => ['nullable', 'string', 'max:20', 'unique:students,nisn,'.$student->id],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'nama_wali' => ['nullable', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'education_program_id' => ['required', 'exists:education_programs,id'],
            'status' => ['required', 'in:'.implode(',', array_keys(Student::STATUS))],
            'tahun_masuk' => ['nullable', 'integer', 'between:2000,2100'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'.($userId ? ','.$userId : '')],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if ((int) $data['education_program_id'] !== (int) $student->education_program_id && $student->enrollments()->exists()) {
            throw ValidationException::withMessages(['education_program_id' => 'Program pendidikan tidak dapat diubah karena peserta didik sudah memiliki riwayat penempatan kelas.']);
        }

        $roleSiswa = Role::where('slug', 'siswa')->firstOrFail();

        DB::transaction(function () use ($data, $student, $roleSiswa) {
            $isActive = in_array($data['status'], ['aktif', 'lulus'], true);
            if ($student->user) {
                $upd = ['name' => $data['nama'], 'username' => $data['username'], 'phone' => ($data['telepon'] ?? null) ?: null, 'is_active' => $isActive];
                if (filled($data['password'] ?? null)) {
                    $upd['password'] = Hash::make($data['password']);
                }
                $student->user->update($upd);
            } else {
                $u = User::create([
                    'name' => $data['nama'],
                    'username' => $data['username'],
                    'phone' => ($data['telepon'] ?? null) ?: null,
                    'password' => Hash::make(($data['password'] ?? null) ?: $data['nis']),
                    'role_id' => $roleSiswa->id,
                    'is_active' => $isActive,
                ]);
                $student->user_id = $u->id;
            }
            unset($data['username'], $data['password']);
            $data['user_id'] = $student->user_id;
            $data['nisn'] = ($data['nisn'] ?? null) ?: null;
            $student->update($data);
        });

        ActivityLogger::log('ubah', 'siswa', "Memperbarui data peserta didik {$student->nama}.", $student);

        return redirect()->route('master.students.show', $student)->with('success', 'Data peserta didik berhasil diperbarui.');
    }

    public function destroy(Request $request, Student $student)
    {
        abort_unless($request->user()->hasPermission('students.manage'), 403);
        if ($student->enrollments()->exists() || $student->reportCards()->exists()) {
            return back()->with('error', 'Peserta didik tidak dapat dihapus karena sudah memiliki riwayat kelas atau rapor. Ubah status menjadi Keluar/Nonaktif.');
        }
        $nama = $student->nama;
        DB::transaction(function () use ($student) {
            $u = $student->user;
            $student->delete();
            $u?->delete();
        });
        ActivityLogger::log('hapus', 'siswa', "Menghapus peserta didik {$nama}.");

        return redirect()->route('master.students.index')->with('success', "Peserta didik {$nama} dihapus.");
    }

    public function export(Request $request): StreamedResponse
    {
        $students = Student::with('program')->orderBy('education_program_id')->orderBy('nama')->get();
        ActivityLogger::log('ekspor', 'siswa', 'Mengekspor data peserta didik ke CSV ('.$students->count().' baris).');

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['nis', 'nisn', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'alamat', 'nama_wali', 'telepon', 'program_kode', 'status', 'tahun_masuk']);
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->nis, $s->nisn, $s->nama, $s->jenis_kelamin, $s->tempat_lahir,
                    $s->tanggal_lahir?->toDateString(), $s->agama, $s->alamat, $s->nama_wali,
                    $s->telepon, $s->program->kode, $s->status, $s->tahun_masuk,
                ]);
            }
            fclose($out);
        }, 'peserta_didik_pkbm_alqudwah.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        abort_unless($request->user()->hasPermission('students.manage'), 403);
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $programs = EducationProgram::pluck('id', 'kode');
        $roleSiswa = Role::where('slug', 'siswa')->firstOrFail();

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $header = null;
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($handle, $programs, $roleSiswa, &$header, &$created, &$updated) {
            while (($row = fgetcsv($handle, 4000, ',')) !== false) {
                if (! $header) {
                    $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $row);

                    continue;
                }
                if (count($row) < 3) {
                    continue;
                }
                $r = array_combine(array_slice($header, 0, count($row)), $row);
                $nis = trim($r['nis'] ?? '');
                $nama = trim($r['nama'] ?? '');
                $progKode = strtolower(trim($r['program_kode'] ?? 'paket_b'));
                if ($nis === '' || $nama === '' || ! isset($programs[$progKode])) {
                    continue;
                }
                $jk = strtoupper(trim($r['jenis_kelamin'] ?? 'L')) === 'P' ? 'P' : 'L';
                $nisn = trim($r['nisn'] ?? '') ?: null;
                $tgl = trim($r['tanggal_lahir'] ?? '');
                $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) ? $tgl : null;

                $existing = Student::where('nis', $nis)->first();
                if ($existing) {
                    $existing->update([
                        'nama' => $nama, 'jenis_kelamin' => $jk,
                        'tempat_lahir' => $r['tempat_lahir'] ?? $existing->tempat_lahir,
                        'tanggal_lahir' => $tgl ?? $existing->tanggal_lahir,
                        'agama' => $r['agama'] ?? $existing->agama,
                        'alamat' => $r['alamat'] ?? $existing->alamat,
                        'nama_wali' => $r['nama_wali'] ?? $existing->nama_wali,
                        'telepon' => $r['telepon'] ?? $existing->telepon,
                    ]);
                    $updated++;
                } else {
                    $username = $nis;
                    $i = 1;
                    while (User::where('username', $username)->exists()) {
                        $username = $nis.'_'.(++$i);
                    }
                    $u = User::create([
                        'name' => $nama, 'username' => $username, 'password' => Hash::make($nis),
                        'role_id' => $roleSiswa->id, 'is_active' => true,
                    ]);
                    if ($nisn && Student::where('nisn', $nisn)->exists()) {
                        $nisn = null;
                    }
                    Student::create([
                        'user_id' => $u->id, 'nis' => $nis, 'nisn' => $nisn, 'nama' => $nama, 'jenis_kelamin' => $jk,
                        'tempat_lahir' => $r['tempat_lahir'] ?? null, 'tanggal_lahir' => $tgl,
                        'agama' => $r['agama'] ?? 'Islam', 'alamat' => $r['alamat'] ?? null,
                        'nama_wali' => $r['nama_wali'] ?? null, 'telepon' => $r['telepon'] ?? null,
                        'education_program_id' => $programs[$progKode],
                        'status' => in_array($r['status'] ?? '', array_keys(Student::STATUS), true) ? $r['status'] : 'aktif',
                        'tahun_masuk' => is_numeric($r['tahun_masuk'] ?? null) ? (int) $r['tahun_masuk'] : (int) date('Y'),
                    ]);
                    $created++;
                }
            }
        });
        fclose($handle);

        ActivityLogger::log('impor', 'siswa', "Impor CSV peserta didik: {$created} baru, {$updated} diperbarui.");

        return back()->with('success', "Impor CSV selesai: {$created} peserta didik baru ditambahkan, {$updated} diperbarui.");
    }
}
