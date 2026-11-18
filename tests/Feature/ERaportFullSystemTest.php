<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Curriculum;
use App\Models\EducationProgram;
use App\Models\ReportCard;
use App\Models\ReportVerification;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ERaportFullSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    public function test_five_roles_exist_and_no_separate_subject_teacher_role(): void
    {
        $slugs = Role::orderBy('id')->pluck('slug')->all();
        $this->assertSame(['super_admin', 'kepala_pkbm', 'operator', 'guru', 'siswa'], $slugs);
        $this->assertDatabaseMissing('roles', ['slug' => 'guru_mapel']);
    }

    public function test_login_and_dashboard_for_all_five_roles(): void
    {
        $operator = User::where('username', 'operator')->firstOrFail();

        // Buat siswa via Operator agar memiliki akun role siswa
        $paketB = EducationProgram::where('kode', 'paket_b')->firstOrFail();
        $this->actingAs($operator)->post(route('master.students.store'), [
            'nis' => '2026001',
            'nisn' => '0098765432',
            'nama' => 'Peserta Uji Satu',
            'jenis_kelamin' => 'L',
            'education_program_id' => $paketB->id,
            'status' => 'aktif',
            'tahun_masuk' => 2026,
            'username' => 'siswa001',
            'password' => 'password123',
        ])->assertRedirect();

        foreach (['superadmin', 'kepalapkbm', 'operator', 'walikelas', 'siswa001'] as $uname) {
            $this->post(route('logout'));
            $res = $this->post(route('login.attempt'), ['username' => $uname, 'password' => 'password123']);
            $res->assertRedirect(route('dashboard'));
            $this->get(route('dashboard'))->assertStatus(200);
        }
    }

    public function test_program_grade_validation_and_program_isolation(): void
    {
        $operator = User::where('username', 'operator')->firstOrFail();
        $paketB = EducationProgram::where('kode', 'paket_b')->firstOrFail();
        $paketC = EducationProgram::where('kode', 'paket_c')->firstOrFail();
        $year = AcademicYear::active();
        $km = Curriculum::firstOrFail();

        // Paket B tidak boleh tingkat 10
        $this->actingAs($operator)->post(route('master.classes.store'), [
            'education_program_id' => $paketB->id,
            'tingkat' => 10,
            'academic_year_id' => $year->id,
            'curriculum_id' => $km->id,
            'status' => 'aktif',
        ])->assertSessionHasErrors('tingkat');

        // Paket C tidak boleh tingkat 7
        $this->actingAs($operator)->post(route('master.classes.store'), [
            'education_program_id' => $paketC->id,
            'tingkat' => 7,
            'academic_year_id' => $year->id,
            'curriculum_id' => $km->id,
            'status' => 'aktif',
        ])->assertSessionHasErrors('tingkat');

        // Buat kelas Paket B Kelas 7 yang sah
        $this->actingAs($operator)->post(route('master.classes.store'), [
            'education_program_id' => $paketB->id,
            'tingkat' => 7,
            'academic_year_id' => $year->id,
            'curriculum_id' => $km->id,
            'status' => 'aktif',
        ])->assertRedirect();

        $kelasB7 = Classroom::where('education_program_id', $paketB->id)->where('tingkat', 7)->firstOrFail();

        // Buat siswa Paket C dan coba masukkan ke kelas Paket B -> harus ditolak!
        $siswaC = Student::create([
            'nis' => 'C001', 'nama' => 'Siswa Paket C', 'jenis_kelamin' => 'P',
            'education_program_id' => $paketC->id, 'status' => 'aktif',
        ]);
        $this->actingAs($operator)->post(route('master.classes.enroll', $kelasB7), [
            'student_ids' => [$siswaC->id],
        ])->assertSessionHasErrors('student_ids');
    }

    public function test_end_to_end_grading_titimangsa_signature_approval_pdf_qr_and_reopen(): void
    {
        $operator = User::where('username', 'operator')->firstOrFail();
        $kepala = User::where('username', 'kepalapkbm')->firstOrFail();
        $waliUser = User::where('username', 'walikelas')->firstOrFail();
        $waliTeacher = Teacher::where('user_id', $waliUser->id)->firstOrFail();

        $paketB = EducationProgram::where('kode', 'paket_b')->firstOrFail();
        $year = AcademicYear::active();
        $semester = Semester::active();
        $km = Curriculum::firstOrFail();

        // 1. Operator buat kelas Paket B Kelas 7 dan tetapkan Wali Kelas
        $this->actingAs($operator)->post(route('master.classes.store'), [
            'education_program_id' => $paketB->id,
            'tingkat' => 7,
            'academic_year_id' => $year->id,
            'curriculum_id' => $km->id,
            'teacher_id' => $waliTeacher->id,
            'status' => 'aktif',
        ])->assertRedirect();
        $kelas = Classroom::firstOrFail();
        $this->assertSame('Paket B Kelas 7 Tahun Ajaran 2026/2027', $kelas->nama);

        // 2. Operator tambah 2 peserta didik Paket B dan tempatkan di kelas
        $this->actingAs($operator)->post(route('master.students.store'), [
            'nis' => 'B7001', 'nisn' => '0011223344', 'nama' => 'Ahmad Fauzi', 'jenis_kelamin' => 'L',
            'education_program_id' => $paketB->id, 'status' => 'aktif', 'tahun_masuk' => 2026,
        ]);
        $this->actingAs($operator)->post(route('master.students.store'), [
            'nis' => 'B7002', 'nisn' => '0011223345', 'nama' => 'Siti Rahmah', 'jenis_kelamin' => 'P',
            'education_program_id' => $paketB->id, 'status' => 'aktif', 'tahun_masuk' => 2026,
        ]);
        $ahmad = Student::where('nis', 'B7001')->firstOrFail();
        $siti = Student::where('nis', 'B7002')->firstOrFail();

        $this->actingAs($operator)->post(route('master.classes.enroll', $kelas), [
            'student_ids' => [$ahmad->id, $siti->id],
        ])->assertRedirect();

        // 3. Operator atur titimangsa rapor
        $this->actingAs($operator)->put(route('reports.settings.update', $semester), [
            'tempat' => 'Cianjur',
            'tanggal_terbit' => '2026-12-20',
        ])->assertRedirect();

        // 4. Wali Kelas input nilai seluruh mata pelajaran di kelas tanggung jawabnya
        $subjects = $kelas->subjects()->get();
        $this->assertGreaterThan(0, $subjects->count());

        // Pertama coba isi sebagian (ASAS kosong) -> nilai_akhir harus tetap null
        $firstSub = $subjects->first();
        $this->actingAs($waliUser)->post(route('academic.scores.store'), [
            'semester_id' => $semester->id,
            'kelas_id' => $kelas->id,
            'mapel_id' => $firstSub->id,
            'scores' => [
                $ahmad->id => ['sumatif' => '80', 'asts' => '85', 'asas' => '', 'deskripsi' => ''],
            ],
        ])->assertRedirect();
        $this->assertDatabaseHas('assessment_scores', [
            'student_id' => $ahmad->id, 'subject_id' => $firstSub->id, 'nilai_akhir' => null,
        ]);

        // Sekarang lengkapi seluruh mata pelajaran untuk Ahmad
        foreach ($subjects as $sub) {
            $this->actingAs($waliUser)->post(route('academic.scores.store'), [
                'semester_id' => $semester->id,
                'kelas_id' => $kelas->id,
                'mapel_id' => $sub->id,
                'scores' => [
                    $ahmad->id => ['sumatif' => '80', 'asts' => '85', 'asas' => '90', 'deskripsi' => 'Menguasai materi dengan sangat baik.'],
                ],
            ])->assertRedirect();
        }
        $this->assertDatabaseHas('assessment_scores', [
            'student_id' => $ahmad->id, 'subject_id' => $firstSub->id, 'nilai_akhir' => 85,
        ]);

        // 5. Wali Kelas isi absensi & catatan wali kelas
        $this->actingAs($waliUser)->post(route('academic.attendance.store'), [
            'semester_id' => $semester->id,
            'kelas_id' => $kelas->id,
            'abs' => [
                $ahmad->id => ['sakit' => 1, 'izin' => 2, 'alpa' => 0],
            ],
        ])->assertRedirect();

        $this->actingAs($waliUser)->put(route('academic.notes.update', $ahmad), [
            'semester_id' => $semester->id,
            'kelas_id' => $kelas->id,
            'perkembangan' => 'Perkembangan akademik meningkat pesat.',
            'sikap' => 'Santun dan disiplin.',
            'motivasi' => 'Semangat belajar tinggi.',
            'saran' => 'Pertahankan prestasi.',
            'catatan_orang_tua' => 'Mohon terus didukung di rumah.',
        ])->assertRedirect();

        // 6. Buat tanda tangan digital Wali Kelas & Kepala PKBM
        $svgSample = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 200"><path d="M10 100 L120 40 L220 110"/></svg>';
        $this->actingAs($waliUser)->post(route('profile.signature.store'), ['svg' => $svgSample])->assertRedirect();
        $this->actingAs($kepala)->post(route('profile.signature.store'), ['svg' => $svgSample])->assertRedirect();

        $cardAhmad = ReportCard::where('student_id', $ahmad->id)->where('semester_id', $semester->id)->firstOrFail();

        // Peserta didik belum boleh melihat rapor yang belum disahkan
        $this->actingAs($ahmad->user)->get(route('reports.show', $cardAhmad))->assertStatus(403);

        // 7. Wali Kelas tanda tangani dan ajukan rapor Ahmad ke Kepala PKBM
        $this->actingAs($waliUser)->post(route('reports.submit', $cardAhmad))->assertRedirect();
        $this->assertSame('menunggu_pengesahan', $cardAhmad->fresh()->status);

        // 8. Kepala PKBM sahkan rapor Ahmad
        $this->actingAs($kepala)->post(route('reports.approve', $cardAhmad))->assertRedirect();
        $cardAhmad->refresh();
        $this->assertSame('disahkan', $cardAhmad->status);
        $this->assertSame(1, $cardAhmad->versi_aktif);
        $this->assertTrue($cardAhmad->is_locked);

        // 9. Cek PDF individual, cetak, cetak massal, ZIP, dan verifikasi QR
        $pdfRes = $this->actingAs($ahmad->user)->get(route('reports.pdf', $cardAhmad));
        $pdfRes->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $pdfRes->getContent());

        // Cek halaman cetak berisi Cover, Biodata Sekolah, Biodata Siswa, dan hanya menampilkan Nilai Akhir (tanpa kolom Sumatif/ASTS/ASAS)
        $printRes = $this->actingAs($ahmad->user)->get(route('reports.print', $cardAhmad));
        $printRes->assertStatus(200)
            ->assertSee('RAPOR PESERTA DIDIK')
            ->assertSee('IDENTITAS SATUAN PENDIDIKAN (LEMBAGA)')
            ->assertSee('KETERANGAN TENTANG DIRI PESERTA DIDIK')
            ->assertSee('LAPORAN HASIL BELAJAR (RAPOR)')
            ->assertSee('Nilai Akhir')
            ->assertSee('Capaian Kompetensi')
            ->assertDontSee('<th style="width:7%">Sumatif</th>', false)
            ->assertDontSee('<th style="width:7%">ASTS</th>', false)
            ->assertDontSee('<th style="width:7%">ASAS</th>', false);

        // Peserta didik lain (Siti) TIDAK BOLEH mengakses rapor Ahmad
        $this->actingAs($siti->user)->get(route('reports.show', $cardAhmad))->assertStatus(403);
        $this->actingAs($siti->user)->get(route('reports.pdf', $cardAhmad))->assertStatus(403);

        // Cetak massal & ZIP oleh Operator
        $this->actingAs($operator)->get(route('reports.class.print', [$kelas, 'semester_id' => $semester->id]))->assertStatus(200);
        $this->actingAs($operator)->get(route('reports.class.pdf', [$kelas, 'semester_id' => $semester->id]))->assertStatus(200);
        $this->actingAs($operator)->get(route('reports.class.zip', [$kelas, 'semester_id' => $semester->id]))->assertStatus(200);

        // Verifikasi QR publik: Valid
        $ver = ReportVerification::where('report_card_id', $cardAhmad->id)->firstOrFail();
        $this->get(route('verification.show', $ver->token))
            ->assertStatus(200)
            ->assertSee('VALID')
            ->assertSee('20 Desember 2026');

        // 10. Kepala PKBM membuka kembali rapor -> versi 1 menjadi TIDAK VALID
        $this->actingAs($kepala)->post(route('reports.reopen', $cardAhmad), [
            'alasan' => 'Koreksi nilai mata pelajaran.',
        ])->assertRedirect();

        $this->assertSame('dibuka_kembali', $cardAhmad->fresh()->status);
        $this->get(route('verification.show', $ver->token))
            ->assertStatus(200)
            ->assertSee('TIDAK VALID');
    }

    public function test_class_promotion_and_graduation_preserve_historical_enrollments(): void
    {
        $operator = User::where('username', 'operator')->firstOrFail();
        $paketB = EducationProgram::where('kode', 'paket_b')->firstOrFail();
        $km = Curriculum::firstOrFail();
        $year1 = AcademicYear::active();

        // Buat tahun ajaran berikutnya 2027/2028
        $this->actingAs($operator)->post(route('master.years.store'), [
            'nama' => '2027/2028',
        ])->assertRedirect();
        $year2 = AcademicYear::where('nama', '2027/2028')->firstOrFail();

        $kelas7 = Classroom::create([
            'nama' => 'Paket B Kelas 7 Tahun Ajaran 2026/2027', 'paralel' => '',
            'education_program_id' => $paketB->id, 'tingkat' => 7,
            'academic_year_id' => $year1->id, 'curriculum_id' => $km->id, 'status' => 'aktif',
        ]);
        $kelas8 = Classroom::create([
            'nama' => 'Paket B Kelas 8 Tahun Ajaran 2027/2028', 'paralel' => '',
            'education_program_id' => $paketB->id, 'tingkat' => 8,
            'academic_year_id' => $year2->id, 'curriculum_id' => $km->id, 'status' => 'aktif',
        ]);

        $siswa = Student::create([
            'nis' => 'B7999', 'nama' => 'Ahmad Riwayat', 'jenis_kelamin' => 'L',
            'education_program_id' => $paketB->id, 'status' => 'aktif', 'tahun_masuk' => 2026,
        ]);
        $enr7 = StudentEnrollment::create([
            'student_id' => $siswa->id, 'class_id' => $kelas7->id,
            'academic_year_id' => $year1->id, 'status' => 'aktif',
        ]);

        // Konfirmasi kenaikan dari Kelas 7 (2026/2027) ke Kelas 8 (2027/2028)
        $this->actingAs($operator)->post(route('master.promotions.process'), [
            'from_class_id' => $kelas7->id,
            'aksi' => 'konfirmasi',
            'items' => [
                $enr7->id => ['keputusan' => 'naik', 'to_class_id' => $kelas8->id, 'catatan' => 'Naik kelas'],
            ],
        ])->assertRedirect();

        // Riwayat kelas 7 TIDAK terhapus, statusnya menjadi 'naik', dan kelas 8 tercatat 'aktif'
        $this->assertDatabaseHas('student_enrollments', ['id' => $enr7->id, 'class_id' => $kelas7->id, 'status' => 'naik']);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $siswa->id, 'class_id' => $kelas8->id, 'status' => 'aktif']);
    }

    public function test_all_modules_pages_and_role_access_restrictions(): void
    {
        $super = User::where('username', 'superadmin')->firstOrFail();
        $operator = User::where('username', 'operator')->firstOrFail();
        $kepala = User::where('username', 'kepalapkbm')->firstOrFail();
        $wali1 = User::where('username', 'walikelas')->firstOrFail();

        // Buat guru kedua & kelasnya untuk menguji pembatasan akses kelas antar Wali Kelas
        $this->actingAs($operator)->post(route('master.teachers.store'), [
            'nip' => '198801012022',
            'nama' => 'Guru Wali Kedua',
            'jenis_kelamin' => 'P',
            'jabatan' => 'Guru / Wali Kelas',
            'username' => 'walikelas2',
            'password' => 'password123',
            'is_active' => 1,
        ])->assertRedirect();

        $teacher2 = Teacher::where('nip', '198801012022')->firstOrFail();
        $paketC = EducationProgram::where('kode', 'paket_c')->firstOrFail();
        $year = AcademicYear::active();
        $km = Curriculum::firstOrFail();

        $kelasC10 = Classroom::create([
            'nama' => 'Paket C Kelas 10 Tahun Ajaran 2026/2027', 'paralel' => '',
            'education_program_id' => $paketC->id, 'tingkat' => 10,
            'academic_year_id' => $year->id, 'curriculum_id' => $km->id,
            'teacher_id' => $teacher2->id, 'status' => 'aktif',
        ]);

        // Wali Kelas 1 TIDAK BOLEH mengakses cetak/input kelas milik Wali Kelas 2
        $this->actingAs($wali1)->get(route('reports.class.print', [$kelasC10, 'semester_id' => Semester::active()->id]))->assertStatus(403);
        // Wali Kelas 1 tidak boleh mengakses menu Super Admin
        $this->actingAs($wali1)->get(route('admin.users.index'))->assertStatus(403);

        // Uji halaman-halaman Master Data & Akademik oleh Operator
        foreach ([
            route('master.school.edit'),
            route('master.years.index'),
            route('master.curricula.index'),
            route('master.subjects.index'),
            route('master.teachers.index'),
            route('master.students.index'),
            route('master.students.export'),
            route('master.classes.index'),
            route('master.classes.show', $kelasC10),
            route('master.promotions.index'),
            route('academic.recap'),
            route('academic.weights.edit'),
            route('reports.index'),
            route('reports.settings'),
            route('reports.archive'),
        ] as $url) {
            $this->actingAs($operator)->get($url)->assertStatus(200);
        }

        // Uji halaman-halaman Super Admin
        foreach ([
            route('admin.users.index'),
            route('admin.roles.index'),
            route('admin.settings.edit'),
            route('admin.backups.index'),
            route('admin.logs.index'),
        ] as $url) {
            $this->actingAs($super)->get($url)->assertStatus(200);
        }

        // Uji halaman Wali Kelas
        foreach ([
            route('academic.myclass'),
            route('academic.scores.index'),
            route('academic.attendance.index'),
            route('academic.notes.index'),
            route('profile.signature'),
        ] as $url) {
            $this->actingAs($wali1)->get($url)->assertStatus(200);
        }

        // Uji halaman Kepala PKBM
        foreach ([
            route('reports.index'),
            route('reports.settings'),
            route('academic.recap'),
            route('profile.signature'),
        ] as $url) {
            $this->actingAs($kepala)->get($url)->assertStatus(200);
        }
    }
}
