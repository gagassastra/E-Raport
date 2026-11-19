<?php

use App\Http\Controllers\Academic\AcademicOverviewController;
use App\Http\Controllers\Academic\AttendanceController;
use App\Http\Controllers\Academic\NoteController;
use App\Http\Controllers\Academic\ScoreController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\AcademicYearController;
use App\Http\Controllers\Master\ClassroomController;
use App\Http\Controllers\Master\CurriculumController;
use App\Http\Controllers\Master\PromotionController;
use App\Http\Controllers\Master\SchoolProfileController;
use App\Http\Controllers\Master\StudentController;
use App\Http\Controllers\Master\SubjectController;
use App\Http\Controllers\Master\TeacherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::get('/logo-lembaga', [SchoolProfileController::class, 'logo'])->name('school.logo');
Route::get('/verifikasi/{token}', [VerificationController::class, 'show'])->name('verification.show');
Route::get('/verifikasi-ttd/{card}/{penanda}', [VerificationController::class, 'signature'])->name('verification.signature');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/keluar', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active', 'password.fresh'])->group(function () {
    Route::get('/dasbor', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/notifikasi/baca', [ProfileController::class, 'notificationsRead'])->name('notifications.read');

    // Profil, Kata Sandi, & Tanda Tangan Digital
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profil/kata-sandi', [ProfileController::class, 'passwordForm'])->name('profile.password');
    Route::put('/profil/kata-sandi', [ProfileController::class, 'passwordUpdate'])->name('profile.password.update');
    Route::get('/profil/tanda-tangan', [ProfileController::class, 'signature'])->name('profile.signature');
    Route::post('/profil/tanda-tangan', [ProfileController::class, 'signatureStore'])->name('profile.signature.store');
    Route::delete('/profil/tanda-tangan', [ProfileController::class, 'signatureDestroy'])->name('profile.signature.destroy');
    Route::get('/profil/tanda-tangan/gambar', [ProfileController::class, 'signatureImage'])->name('profile.signature.image');

    // Portal Peserta Didik
    Route::get('/portal/rapor', [ReportCardController::class, 'portal'])->middleware('permission:reports.own')->name('portal.reports');

    // Akademik & Wali Kelas
    Route::prefix('akademik')->name('academic.')->group(function () {
        Route::get('/kelas-saya', [AcademicOverviewController::class, 'myClass'])->middleware('permission:scores.input,reports.prepare')->name('myclass');
        Route::get('/nilai', [ScoreController::class, 'index'])->middleware('permission:scores.input')->name('scores.index');
        Route::post('/nilai', [ScoreController::class, 'store'])->middleware('permission:scores.input')->name('scores.store');
        Route::get('/nilai/ekspor', [ScoreController::class, 'export'])->middleware('permission:scores.input')->name('scores.export');
        Route::post('/nilai/impor', [ScoreController::class, 'import'])->middleware('permission:scores.input')->name('scores.import');
        Route::get('/absensi', [AttendanceController::class, 'index'])->middleware('permission:attendance.input')->name('attendance.index');
        Route::post('/absensi', [AttendanceController::class, 'store'])->middleware('permission:attendance.input')->name('attendance.store');
        Route::get('/catatan', [NoteController::class, 'index'])->middleware('permission:notes.input')->name('notes.index');
        Route::get('/catatan/{student}/edit', [NoteController::class, 'edit'])->middleware('permission:notes.input')->name('notes.edit');
        Route::put('/catatan/{student}', [NoteController::class, 'update'])->middleware('permission:notes.input')->name('notes.update');

        Route::get('/rekap-nilai', [AcademicOverviewController::class, 'recap'])->middleware('permission:scores.view')->name('recap');
        Route::get('/bobot-penilaian', [AcademicOverviewController::class, 'weightsEdit'])->middleware('permission:weights.manage')->name('weights.edit');
        Route::put('/bobot-penilaian', [AcademicOverviewController::class, 'weightsUpdate'])->middleware('permission:weights.manage')->name('weights.update');
    });

    // Rapor, Pengesahan, PDF, Cetak & Titimangsa
    Route::get('/rapor', [ReportCardController::class, 'index'])->middleware('permission:reports.prepare,reports.view_all,reports.approve')->name('reports.index');
    Route::get('/rapor/kelas/{class}/cetak', [ReportCardController::class, 'classPrint'])->name('reports.class.print');
    Route::get('/rapor/kelas/{class}/pdf', [ReportCardController::class, 'classPdf'])->name('reports.class.pdf');
    Route::get('/rapor/kelas/{class}/zip', [ReportCardController::class, 'classZip'])->name('reports.class.zip');
    Route::post('/rapor/kelas/{class}/ajukan-semua', [ReportCardController::class, 'submitAll'])->middleware('permission:reports.prepare')->name('reports.class.submit-all');
    Route::post('/rapor/kelas/{class}/sahkan-semua', [ReportCardController::class, 'approveAll'])->middleware('permission:reports.approve')->name('reports.class.approve-all');

    Route::get('/rapor/{card}', [ReportCardController::class, 'show'])->name('reports.show');
    Route::get('/rapor/{card}/cetak', [ReportCardController::class, 'print'])->name('reports.print');
    Route::get('/rapor/{card}/pdf', [ReportCardController::class, 'pdf'])->name('reports.pdf');
    Route::post('/rapor/{card}/ajukan', [ReportCardController::class, 'submit'])->middleware('permission:reports.prepare')->name('reports.submit');
    Route::post('/rapor/{card}/sahkan', [ReportCardController::class, 'approve'])->middleware('permission:reports.approve')->name('reports.approve');
    Route::post('/rapor/{card}/tolak', [ReportCardController::class, 'reject'])->middleware('permission:reports.approve')->name('reports.reject');
    Route::post('/rapor/{card}/buka-kembali', [ReportCardController::class, 'reopen'])->middleware('permission:reports.reopen')->name('reports.reopen');
    Route::post('/rapor/{card}/batalkan', [ReportCardController::class, 'cancel'])->middleware('permission:reports.reopen')->name('reports.cancel');

    Route::get('/pengaturan-rapor/titimangsa', [ReportCardController::class, 'settings'])->middleware('permission:reports.settings')->name('reports.settings');
    Route::put('/pengaturan-rapor/titimangsa/{semester}', [ReportCardController::class, 'settingsUpdate'])->middleware('permission:reports.settings')->name('reports.settings.update');
    Route::get('/arsip-rapor', [ReportCardController::class, 'archive'])->middleware('permission:reports.view_all,reports.prepare')->name('reports.archive');

    // Data Induk (Master Data)
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/lembaga', [SchoolProfileController::class, 'edit'])->middleware('permission:school.manage')->name('school.edit');
        Route::put('/lembaga', [SchoolProfileController::class, 'update'])->middleware('permission:school.manage')->name('school.update');
        Route::post('/lembaga/impor', [SchoolProfileController::class, 'importFromWebsite'])->middleware('permission:school.manage')->name('school.import');

        Route::get('/tahun-ajaran', [AcademicYearController::class, 'index'])->middleware('permission:years.manage')->name('years.index');
        Route::post('/tahun-ajaran', [AcademicYearController::class, 'store'])->middleware('permission:years.manage')->name('years.store');
        Route::put('/tahun-ajaran/{year}', [AcademicYearController::class, 'update'])->middleware('permission:years.manage')->name('years.update');
        Route::post('/semester/{semester}/aktifkan', [AcademicYearController::class, 'activateSemester'])->middleware('permission:years.manage')->name('years.activate-semester');
        Route::post('/tahun-ajaran/{year}/nonaktifkan', [AcademicYearController::class, 'deactivate'])->middleware('permission:years.manage')->name('years.deactivate');
        Route::post('/tahun-ajaran/{year}/arsip', [AcademicYearController::class, 'archive'])->middleware('permission:years.manage')->name('years.archive');

        Route::get('/kurikulum', [CurriculumController::class, 'index'])->middleware('permission:curricula.manage')->name('curricula.index');
        Route::post('/kurikulum', [CurriculumController::class, 'store'])->middleware('permission:curricula.manage')->name('curricula.store');
        Route::put('/kurikulum/{curriculum}', [CurriculumController::class, 'update'])->middleware('permission:curricula.manage')->name('curricula.update');
        Route::delete('/kurikulum/{curriculum}', [CurriculumController::class, 'destroy'])->middleware('permission:curricula.manage')->name('curricula.destroy');

        Route::get('/mata-pelajaran', [SubjectController::class, 'index'])->middleware('permission:subjects.manage')->name('subjects.index');
        Route::post('/mata-pelajaran', [SubjectController::class, 'store'])->middleware('permission:subjects.manage')->name('subjects.store');
        Route::put('/mata-pelajaran/{subject}', [SubjectController::class, 'update'])->middleware('permission:subjects.manage')->name('subjects.update');
        Route::delete('/mata-pelajaran/{subject}', [SubjectController::class, 'destroy'])->middleware('permission:subjects.manage')->name('subjects.destroy');

        Route::get('/guru', [TeacherController::class, 'index'])->middleware('permission:teachers.view,teachers.manage')->name('teachers.index');
        Route::get('/guru/tambah', [TeacherController::class, 'create'])->middleware('permission:teachers.manage')->name('teachers.create');
        Route::post('/guru', [TeacherController::class, 'store'])->middleware('permission:teachers.manage')->name('teachers.store');
        Route::post('/guru/impor', [TeacherController::class, 'importFromWebsite'])->middleware('permission:teachers.manage')->name('teachers.import');
        Route::get('/guru/{teacher}/edit', [TeacherController::class, 'edit'])->middleware('permission:teachers.manage')->name('teachers.edit');
        Route::put('/guru/{teacher}', [TeacherController::class, 'update'])->middleware('permission:teachers.manage')->name('teachers.update');
        Route::delete('/guru/{teacher}', [TeacherController::class, 'destroy'])->middleware('permission:teachers.manage')->name('teachers.destroy');

        Route::get('/peserta-didik', [StudentController::class, 'index'])->middleware('permission:students.view,students.manage')->name('students.index');
        Route::get('/peserta-didik/tambah', [StudentController::class, 'create'])->middleware('permission:students.manage')->name('students.create');
        Route::post('/peserta-didik', [StudentController::class, 'store'])->middleware('permission:students.manage')->name('students.store');
        Route::get('/peserta-didik/ekspor-csv', [StudentController::class, 'export'])->middleware('permission:students.view,students.manage')->name('students.export');
        Route::post('/peserta-didik/impor-csv', [StudentController::class, 'import'])->middleware('permission:students.manage')->name('students.import');
        Route::get('/peserta-didik/{student}', [StudentController::class, 'show'])->middleware('permission:students.view,students.manage')->name('students.show');
        Route::get('/peserta-didik/{student}/edit', [StudentController::class, 'edit'])->middleware('permission:students.manage')->name('students.edit');
        Route::put('/peserta-didik/{student}', [StudentController::class, 'update'])->middleware('permission:students.manage')->name('students.update');
        Route::delete('/peserta-didik/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.manage')->name('students.destroy');

        Route::get('/kelas', [ClassroomController::class, 'index'])->middleware('permission:classes.view,classes.manage')->name('classes.index');
        Route::post('/kelas', [ClassroomController::class, 'store'])->middleware('permission:classes.manage')->name('classes.store');
        Route::get('/kelas/{class}', [ClassroomController::class, 'show'])->middleware('permission:classes.view,classes.manage')->name('classes.show');
        Route::put('/kelas/{class}', [ClassroomController::class, 'update'])->middleware('permission:classes.manage')->name('classes.update');
        Route::delete('/kelas/{class}', [ClassroomController::class, 'destroy'])->middleware('permission:classes.manage')->name('classes.destroy');
        Route::put('/kelas/{class}/wali-kelas', [ClassroomController::class, 'assignTeacher'])->middleware('permission:classes.manage')->name('classes.assign-teacher');
        Route::post('/kelas/{class}/peserta-didik', [ClassroomController::class, 'enrollStudents'])->middleware('permission:classes.manage')->name('classes.enroll');
        Route::delete('/kelas/{class}/peserta-didik/{enrollment}', [ClassroomController::class, 'removeStudent'])->middleware('permission:classes.manage')->name('classes.remove-student');

        Route::get('/kenaikan-kelas', [PromotionController::class, 'index'])->middleware('permission:promotions.manage')->name('promotions.index');
        Route::post('/kenaikan-kelas/proses', [PromotionController::class, 'process'])->middleware('permission:promotions.manage')->name('promotions.process');
        Route::post('/kenaikan-kelas/pindah', [PromotionController::class, 'transfer'])->middleware('permission:promotions.manage')->name('promotions.transfer');
    });

    // Sistem & Super Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->middleware('permission:users.manage')->name('users.index');
        Route::post('/pengguna', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage')->name('users.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');

        Route::get('/pengaturan', [SettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
        Route::put('/pengaturan', [SettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');

        Route::get('/backup', [BackupController::class, 'index'])->middleware('permission:backup.manage')->name('backups.index');
        Route::post('/backup', [BackupController::class, 'store'])->middleware('permission:backup.manage')->name('backups.store');
        Route::get('/backup/{file}/unduh', [BackupController::class, 'download'])->middleware('permission:backup.manage')->name('backups.download');
        Route::delete('/backup/{file}', [BackupController::class, 'destroy'])->middleware('permission:backup.manage')->name('backups.destroy');

        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->middleware('permission:logs.view')->name('logs.index');
    });
});
