<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 40)->unique();
            $t->string('nama', 80);
            $t->string('deskripsi')->nullable();
            $t->boolean('is_system')->default(true);
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('nama', 120);
            $t->string('kelompok', 60)->index();
            $t->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });

        // Perluasan tabel users bawaan Laravel (tidak membuat tabel duplikat)
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 60)->nullable()->unique()->after('name');
            $t->foreignId('role_id')->nullable()->after('password')->constrained('roles')->restrictOnDelete();
            $t->string('phone', 30)->nullable()->after('email');
            $t->boolean('is_active')->default(true)->after('role_id');
            $t->boolean('must_change_password')->default(false)->after('is_active');
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 100)->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('user_nama', 120)->nullable();
            $t->string('aksi', 60)->index();
            $t->string('modul', 60)->nullable()->index();
            $t->string('subject_type', 120)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->text('deskripsi');
            $t->json('properti')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['role_id']);
            $t->dropColumn(['username', 'role_id', 'phone', 'is_active', 'must_change_password', 'last_login_at', 'last_login_ip']);
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
