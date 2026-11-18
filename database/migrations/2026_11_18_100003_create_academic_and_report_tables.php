<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_weights', function (Blueprint $t) {
            $t->id();
            $t->foreignId('semester_id')->nullable()->unique()->constrained()->cascadeOnDelete(); // null = bobot bawaan
            $t->string('mode', 10)->default('rata');  // rata | bobot
            $t->decimal('bobot_sumatif', 5, 2)->default(33.34);
            $t->decimal('bobot_asts', 5, 2)->default(33.33);
            $t->decimal('bobot_asas', 5, 2)->default(33.33);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('assessment_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('subject_id')->constrained()->restrictOnDelete();
            $t->foreignId('education_program_id')->constrained()->restrictOnDelete();
            $t->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->decimal('sumatif', 5, 2)->nullable();
            $t->decimal('asts', 5, 2)->nullable();
            $t->decimal('asas', 5, 2)->nullable();
            $t->unsignedTinyInteger('nilai_akhir')->nullable();
            $t->text('deskripsi')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['student_id', 'class_id', 'subject_id', 'semester_id'], 'scores_unik');
            $t->index(['class_id', 'semester_id', 'subject_id']);
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('sakit')->default(0);
            $t->unsignedSmallInteger('izin')->default(0);
            $t->unsignedSmallInteger('alpa')->default(0);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['student_id', 'class_id', 'semester_id'], 'absensi_unik');
        });

        Schema::create('homeroom_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->text('perkembangan')->nullable();
            $t->text('sikap')->nullable();
            $t->text('motivasi')->nullable();
            $t->text('saran')->nullable();
            $t->text('catatan_orang_tua')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['student_id', 'class_id', 'semester_id'], 'catatan_unik');
        });

        Schema::create('report_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('semester_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('tempat', 100);
            $t->date('tanggal_terbit');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('teacher_signatures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('tipe', 10);               // gambar | unggah
            $t->string('mime', 40);
            $t->string('file_path');
            $t->string('hash', 64);
            $t->timestamps();
        });

        Schema::create('report_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->string('status', 40)->default('belum_diisi')->index();
            $t->unsignedInteger('versi_aktif')->default(0);
            $t->boolean('is_locked')->default(false);
            $t->foreignId('guru_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('guru_signed_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('kepala_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('catatan_perbaikan')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'semester_id']);
            $t->index(['class_id', 'semester_id']);
        });

        Schema::create('report_card_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode_mapel', 30);
            $t->string('nama_mapel', 150);
            $t->unsignedSmallInteger('urutan')->default(1);
            $t->decimal('sumatif', 5, 2)->nullable();
            $t->decimal('asts', 5, 2)->nullable();
            $t->decimal('asas', 5, 2)->nullable();
            $t->unsignedTinyInteger('nilai_akhir')->nullable();
            $t->text('deskripsi')->nullable();
            $t->timestamps();
            $t->index('report_card_id');
        });

        Schema::create('report_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('versi');
            $t->string('status', 20)->default('berlaku'); // berlaku | tidak_berlaku
            $t->longText('snapshot');                      // JSON dokumen resmi
            $t->string('pdf_path')->nullable();
            $t->string('pdf_hash', 64)->nullable();
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diterbitkan_at')->nullable();
            $t->timestamp('dinyatakan_tidak_berlaku_at')->nullable();
            $t->string('alasan_tidak_berlaku')->nullable();
            $t->timestamps();
            $t->unique(['report_card_id', 'versi']);
        });

        Schema::create('report_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $t->string('token', 64)->unique();
            $t->boolean('is_valid')->default(true);
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedInteger('jumlah_dicek')->default(0);
            $t->timestamp('terakhir_dicek_at')->nullable();
            $t->timestamps();
        });

        Schema::create('report_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('versi')->default(0);
            $t->string('aksi', 30);   // tanda_tangan_guru, diajukan, disahkan, ditolak, dibuka_kembali, dibatalkan
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama_penanda', 150)->nullable();
            $t->string('jabatan', 100)->nullable();
            $t->string('signature_hash', 64)->nullable();
            $t->text('catatan')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('waktu')->useCurrent();
            $t->index(['report_card_id', 'waktu']);
        });

        Schema::create('grade_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $t->foreignId('from_class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('to_class_id')->nullable()->constrained('classes')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->string('keputusan', 20);           // naik | tinggal | lulus | pindah
            $t->string('status', 20)->default('draft'); // draft | dikonfirmasi
            $t->string('catatan')->nullable();
            $t->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('dikonfirmasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('dikonfirmasi_at')->nullable();
            $t->timestamps();
            $t->index(['from_class_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['grade_decisions', 'report_approvals', 'report_verifications', 'report_versions', 'report_card_details', 'report_cards', 'teacher_signatures', 'report_settings', 'homeroom_notes', 'attendance_records', 'assessment_scores', 'grade_weights'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
