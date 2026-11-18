<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\EducationProgram;
use App\Models\GradeWeight;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder data referensi sistem dan akun akses awal.
 * Tidak mengarang NPSN, alamat, nama kepala, maupun data fiktif yang seolah-olah data asli PKBM.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        foreach (config('erapor.roles') as $slug => $info) {
            $roles[$slug] = Role::firstOrCreate(
                ['slug' => $slug],
                ['nama' => $info['nama'], 'deskripsi' => $info['deskripsi'], 'is_system' => true]
            );
        }

        foreach (config('erapor.permissions') as $slug => [$nama, $kelompok, $defaultRoles]) {
            $perm = Permission::firstOrCreate(['slug' => $slug], ['nama' => $nama, 'kelompok' => $kelompok]);
            foreach ($defaultRoles as $r) {
                $roles[$r]->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        $paketB = EducationProgram::firstOrCreate(
            ['kode' => 'paket_b'],
            ['nama' => 'Paket B', 'setara' => 'Setara SMP', 'tingkat_awal' => 7, 'tingkat_akhir' => 9, 'is_active' => true]
        );
        $paketC = EducationProgram::firstOrCreate(
            ['kode' => 'paket_c'],
            ['nama' => 'Paket C', 'setara' => 'Setara SMA', 'tingkat_awal' => 10, 'tingkat_akhir' => 12, 'is_active' => true]
        );

        $km = Curriculum::firstOrCreate(
            ['kode' => 'KM'],
            ['nama' => 'Kurikulum Merdeka', 'deskripsi' => 'Kurikulum Kesetaraan Paket B dan Paket C.', 'is_active' => true]
        );

        // Mata pelajaran referensi Kurikulum Kesetaraan (dapat diubah/ditambah oleh Operator)
        $mapelPaketB = [
            ['PAI', 'Pendidikan Agama dan Budi Pekerti'],
            ['PPKN', 'Pendidikan Pancasila'],
            ['BIND', 'Bahasa Indonesia'],
            ['MTK', 'Matematika'],
            ['IPA', 'Ilmu Pengetahuan Alam'],
            ['IPS', 'Ilmu Pengetahuan Sosial'],
            ['BING', 'Bahasa Inggris'],
            ['PJOK', 'Pendidikan Jasmani, Olahraga, dan Kesehatan'],
            ['PBD', 'Pemberdayaan'],
            ['KTR', 'Keterampilan'],
        ];
        foreach ([7, 8, 9] as $tingkat) {
            foreach ($mapelPaketB as $idx => [$kode, $nama]) {
                Subject::firstOrCreate(
                    ['kode' => $kode, 'education_program_id' => $paketB->id, 'tingkat' => $tingkat, 'curriculum_id' => $km->id],
                    ['nama' => $nama, 'urutan' => $idx + 1, 'is_active' => true]
                );
            }
        }

        $mapelPaketC = [
            ['PAI', 'Pendidikan Agama dan Budi Pekerti'],
            ['PPKN', 'Pendidikan Pancasila'],
            ['BIND', 'Bahasa Indonesia'],
            ['MTK', 'Matematika'],
            ['BING', 'Bahasa Inggris'],
            ['SEJ', 'Sejarah'],
            ['SOS', 'Sosiologi'],
            ['EKO', 'Ekonomi'],
            ['GEO', 'Geografi'],
            ['PBD', 'Pemberdayaan'],
            ['KTR', 'Keterampilan'],
        ];
        foreach ([10, 11, 12] as $tingkat) {
            foreach ($mapelPaketC as $idx => [$kode, $nama]) {
                Subject::firstOrCreate(
                    ['kode' => $kode, 'education_program_id' => $paketC->id, 'tingkat' => $tingkat, 'curriculum_id' => $km->id],
                    ['nama' => $nama, 'urutan' => $idx + 1, 'is_active' => true]
                );
            }
        }

        if (AcademicYear::count() === 0) {
            $year = AcademicYear::create([
                'nama' => '2026/2027',
                'tanggal_mulai' => '2026-07-13',
                'tanggal_selesai' => '2027-06-26',
                'is_active' => true,
                'is_archived' => false,
            ]);
            Semester::create(['academic_year_id' => $year->id, 'jenis' => 'ganjil', 'nama' => 'Semester Ganjil', 'is_active' => true]);
            Semester::create(['academic_year_id' => $year->id, 'jenis' => 'genap', 'nama' => 'Semester Genap', 'is_active' => false]);
        }

        GradeWeight::firstOrCreate(
            ['semester_id' => null],
            ['mode' => 'rata', 'bobot_sumatif' => 33.34, 'bobot_asts' => 33.33, 'bobot_asas' => 33.33]
        );

        // Akun awal sistem (bukan identitas fiktif)
        User::firstOrCreate(
            ['username' => 'superadmin'],
            ['name' => 'Super Administrator', 'email' => null, 'password' => Hash::make('password123'), 'role_id' => $roles['super_admin']->id, 'is_active' => true]
        );
        $kepala = User::firstOrCreate(
            ['username' => 'kepalapkbm'],
            ['name' => 'Kepala PKBM', 'email' => null, 'password' => Hash::make('password123'), 'role_id' => $roles['kepala_pkbm']->id, 'is_active' => true]
        );
        User::firstOrCreate(
            ['username' => 'operator'],
            ['name' => 'Operator Sekolah', 'email' => null, 'password' => Hash::make('password123'), 'role_id' => $roles['operator']->id, 'is_active' => true]
        );
        $waliUser = User::firstOrCreate(
            ['username' => 'walikelas'],
            ['name' => 'Guru / Wali Kelas', 'email' => null, 'password' => Hash::make('password123'), 'role_id' => $roles['guru']->id, 'is_active' => true]
        );
        Teacher::firstOrCreate(
            ['user_id' => $waliUser->id],
            ['nama' => $waliUser->name, 'jenis_kelamin' => 'L', 'jabatan' => 'Guru / Wali Kelas', 'is_active' => true]
        );

        if (SchoolProfile::count() === 0) {
            SchoolProfile::create([
                'nama_lembaga' => 'PKBM AL-QUDWAH',
                'jenis_lembaga' => 'Pendidikan Nonformal',
                'kepala_user_id' => $kepala->id,
            ]);
        }
    }
}
