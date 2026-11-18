<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $teachers = Teacher::with(['user', 'classes' => fn ($q) => $q->where('status', 'aktif')->with('academicYear')])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', '%'.$request->query('q').'%')->orWhere('nip', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'aktif'))
            ->orderBy('nama')
            ->paginate(20)->withQueryString();

        return view('master.teachers', compact('teachers'));
    }

    public function create()
    {
        return view('master.teacher-form', ['teacher' => new Teacher(['is_active' => true, 'jenis_kelamin' => 'L', 'jabatan' => 'Guru / Wali Kelas'])]);
    }

    public function store(Request $request)
    {
        $canManage = $request->user()->hasPermission('teachers.manage');
        abort_unless($canManage, 403);

        $data = $request->validate([
            'nip' => ['nullable', 'string', 'max:40', 'unique:teachers,nip'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $roleGuru = Role::where('slug', 'guru')->firstOrFail();

        $teacher = DB::transaction(function () use ($data, $request, $roleGuru) {
            $isActive = $request->boolean('is_active', true);
            $user = User::create([
                'name' => $data['nama'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['telepon'] ?? null,
                'password' => Hash::make($data['password']),
                'role_id' => $roleGuru->id,
                'is_active' => $isActive,
            ]);

            return Teacher::create([
                'user_id' => $user->id,
                'nip' => ($data['nip'] ?? null) ?: null,
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'alamat' => $data['alamat'] ?? null,
                'telepon' => $data['telepon'] ?? null,
                'email' => $data['email'] ?? null,
                'jabatan' => ($data['jabatan'] ?? null) ?: 'Guru / Wali Kelas',
                'is_active' => $isActive,
            ]);
        });

        ActivityLogger::log('tambah', 'guru', "Menambahkan data Guru/Wali Kelas {$teacher->nama}.", $teacher);

        return redirect()->route('master.teachers.index')->with('success', "Data Guru/Wali Kelas {$teacher->nama} beserta akun login berhasil dibuat.");
    }

    public function edit(Teacher $teacher)
    {
        $teacher->load('user');

        return view('master.teacher-form', compact('teacher'));
    }

    public function update(Request $request, Teacher $teacher)
    {
        abort_unless($request->user()->hasPermission('teachers.manage'), 403);
        $userId = $teacher->user_id;

        $data = $request->validate([
            'nip' => ['nullable', 'string', 'max:40', 'unique:teachers,nip,'.$teacher->id],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'.($userId ? ','.$userId : '')],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'.($userId ? ','.$userId : '')],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $roleGuru = Role::where('slug', 'guru')->firstOrFail();

        DB::transaction(function () use ($data, $request, $teacher, $roleGuru) {
            $isActive = $request->boolean('is_active');
            if ($teacher->user) {
                $upd = [
                    'name' => $data['nama'],
                    'username' => $data['username'],
                    'email' => ($data['email'] ?? null) ?: null,
                    'phone' => ($data['telepon'] ?? null) ?: null,
                    'is_active' => $isActive,
                ];
                if (filled($data['password'] ?? null)) {
                    $upd['password'] = Hash::make($data['password']);
                }
                $teacher->user->update($upd);
            } else {
                $u = User::create([
                    'name' => $data['nama'],
                    'username' => $data['username'],
                    'email' => ($data['email'] ?? null) ?: null,
                    'phone' => ($data['telepon'] ?? null) ?: null,
                    'password' => Hash::make(($data['password'] ?? null) ?: 'guru12345'),
                    'role_id' => $roleGuru->id,
                    'is_active' => $isActive,
                ]);
                $teacher->user_id = $u->id;
            }

            $teacher->update([
                'user_id' => $teacher->user_id,
                'nip' => ($data['nip'] ?? null) ?: null,
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'alamat' => ($data['alamat'] ?? null) ?: null,
                'telepon' => ($data['telepon'] ?? null) ?: null,
                'email' => ($data['email'] ?? null) ?: null,
                'jabatan' => ($data['jabatan'] ?? null) ?: 'Guru / Wali Kelas',
                'is_active' => $isActive,
            ]);
        });

        ActivityLogger::log('ubah', 'guru', "Memperbarui data Guru/Wali Kelas {$teacher->nama}.", $teacher);

        return redirect()->route('master.teachers.index')->with('success', 'Data Guru/Wali Kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, Teacher $teacher)
    {
        abort_unless($request->user()->hasPermission('teachers.manage'), 403);
        if ($teacher->classes()->exists()) {
            return back()->with('error', 'Guru tidak dapat dihapus karena masih atau pernah ditugaskan sebagai Wali Kelas. Nonaktifkan statusnya jika sudah tidak aktif.');
        }
        $nama = $teacher->nama;
        DB::transaction(function () use ($teacher) {
            $user = $teacher->user;
            $teacher->delete();
            $user?->delete();
        });
        ActivityLogger::log('hapus', 'guru', "Menghapus data Guru/Wali Kelas {$nama}.");

        return back()->with('success', "Data Guru {$nama} berhasil dihapus.");
    }

    /** Impor satu kali dari tabel `pkbm_alqudwah.tutors` (tanpa mengubah database website). */
    public function importFromWebsite(Request $request)
    {
        abort_unless($request->user()->hasPermission('teachers.manage'), 403);
        try {
            $rows = DB::select('SELECT * FROM `pkbm_alqudwah`.`tutors` ORDER BY urutan ASC, id ASC');
        } catch (\Throwable) {
            $rows = [];
        }
        if (! $rows) {
            return back()->with('warning', 'Tidak ditemukan data tutor pada database website pkbm_alqudwah.');
        }
        $roleGuru = Role::where('slug', 'guru')->firstOrFail();
        $imported = 0;

        DB::transaction(function () use ($rows, $roleGuru, &$imported) {
            foreach ($rows as $r) {
                $nama = trim((string) ($r->nama ?? ''));
                if ($nama === '' || Teacher::where('nama', $nama)->exists()) {
                    continue;
                }
                $baseUser = Str::slug(Str::words($nama, 2, ''), '');
                $username = $baseUser ?: 'guru';
                $n = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $baseUser.(++$n);
                }
                $nip = filled($r->nuptk ?? null) && ! Teacher::where('nip', $r->nuptk)->exists() ? $r->nuptk : null;
                $email = filled($r->email ?? null) && ! User::where('email', $r->email)->exists() ? $r->email : null;
                $jk = in_array(strtoupper((string) ($r->jenis_kelamin ?? '')), ['L', 'P'], true) ? strtoupper($r->jenis_kelamin) : 'L';

                $u = User::create([
                    'name' => $nama,
                    'username' => $username,
                    'email' => $email,
                    'phone' => $r->no_hp ?? null,
                    'password' => Hash::make($username.'123'),
                    'role_id' => $roleGuru->id,
                    'is_active' => (bool) ($r->is_active ?? true),
                ]);

                Teacher::create([
                    'user_id' => $u->id,
                    'nip' => $nip,
                    'nama' => $nama,
                    'jenis_kelamin' => $jk,
                    'telepon' => $r->no_hp ?? null,
                    'email' => $email,
                    'jabatan' => $r->jabatan ?? 'Guru / Wali Kelas',
                    'is_active' => (bool) ($r->is_active ?? true),
                ]);
                $imported++;
            }
        });

        ActivityLogger::log('impor', 'guru', "Mengimpor {$imported} data guru/tutor dari database website pkbm_alqudwah.");

        return back()->with('success', "{$imported} data Guru/Wali Kelas berhasil disalin dari database website (kata sandi awal: nama_pengguna + 123).");
    }
}
