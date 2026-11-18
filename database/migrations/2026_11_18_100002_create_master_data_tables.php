<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('nama_lembaga')->default('PKBM AL-QUDWAH');
            $t->string('jenis_lembaga')->default('Pendidikan Nonformal');
            $t->string('npsn', 20)->nullable();
            $t->string('alamat')->nullable();
            $t->string('desa')->nullable();
            $t->string('kecamatan')->nullable();
            $t->string('kabupaten')->nullable();
            $t->string('provinsi')->nullable();
            $t->string('kode_pos', 10)->nullable();
            $t->string('telepon', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('website')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('kepala_nama')->nullable();
            $t->string('kepala_identitas_label', 30)->nullable();
            $t->string('kepala_identitas')->nullable();
            $t->foreignId('kepala_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('education_programs', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 20)->unique();      // paket_b, paket_c
            $t->string('nama', 60);                // Paket B
            $t->string('setara', 60);              // Setara SMP
            $t->unsignedTinyInteger('tingkat_awal');
            $t->unsignedTinyInteger('tingkat_akhir');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 20)->unique();      // 2026/2027
            $t->date('tanggal_mulai')->nullable();
            $t->date('tanggal_selesai')->nullable();
            $t->boolean('is_active')->default(false);
            $t->boolean('is_archived')->default(false);
            $t->timestamps();
        });

        Schema::create('semesters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->string('jenis', 10);               // ganjil | genap
            $t->string('nama', 40);
            $t->boolean('is_active')->default(false);
            $t->timestamps();
            $t->unique(['academic_year_id', 'jenis']);
        });

        Schema::create('curricula', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->string('nama', 120);
            $t->text('deskripsi')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30);
            $t->string('nama', 150);
            $t->foreignId('education_program_id')->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('tingkat');
            $t->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $t->unsignedSmallInteger('urutan')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['kode', 'education_program_id', 'tingkat', 'curriculum_id'], 'subjects_unik');
            $t->index(['education_program_id', 'tingkat', 'curriculum_id']);
        });

        Schema::create('teachers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('nip', 40)->nullable()->unique();
            $t->string('nama');
            $t->string('jenis_kelamin', 1);
            $t->text('alamat')->nullable();
            $t->string('telepon', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('jabatan', 100)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('nis', 30)->unique();
            $t->string('nisn', 20)->nullable()->unique();
            $t->string('nama');
            $t->string('jenis_kelamin', 1);
            $t->string('tempat_lahir', 100)->nullable();
            $t->date('tanggal_lahir')->nullable();
            $t->string('agama', 30)->nullable();
            $t->text('alamat')->nullable();
            $t->string('nama_wali')->nullable();
            $t->string('telepon', 30)->nullable();
            $t->foreignId('education_program_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('aktif')->index(); // aktif, lulus, pindah, keluar, nonaktif
            $t->unsignedSmallInteger('tahun_masuk')->nullable();
            $t->timestamps();
        });

        Schema::create('classes', function (Blueprint $t) {
            $t->id();
            $t->string('nama');
            $t->string('paralel', 20)->default('');
            $t->foreignId('education_program_id')->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('tingkat');
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $t->string('status', 20)->default('aktif'); // aktif, nonaktif, arsip
            $t->timestamps();
            $t->unique(['education_program_id', 'tingkat', 'academic_year_id', 'paralel'], 'classes_unik');
            $t->index('teacher_id');
        });

        Schema::create('student_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('aktif'); // aktif, naik, tinggal, lulus, pindah, keluar
            $t->date('tanggal_masuk')->nullable();
            $t->date('tanggal_keluar')->nullable();
            $t->string('catatan')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'class_id']);
            $t->index(['class_id', 'status']);
            $t->index(['student_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        foreach (['student_enrollments', 'classes', 'students', 'teachers', 'subjects', 'curricula', 'semesters', 'academic_years', 'education_programs', 'school_profiles'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
