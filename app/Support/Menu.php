<?php

namespace App\Support;

use App\Models\User;

/** Definisi menu sidebar. Item hanya tampil bila pengguna memiliki salah satu permission yang diminta. */
class Menu
{
    public static function for(User $user): array
    {
        $groups = [
            ['label' => null, 'items' => [
                ['Dasbor', 'dashboard', 'home', null, 'dashboard'],
            ]],
            ['label' => 'Portal Peserta Didik', 'items' => [
                ['Rapor Saya', 'portal.reports', 'file-check', ['reports.own'], 'portal.*'],
                ['Profil Saya', 'profile.show', 'user', ['reports.own'], 'profile.*'],
            ]],
            ['label' => 'Wali Kelas', 'items' => [
                ['Kelas Saya', 'academic.myclass', 'class', ['scores.input', 'reports.prepare'], 'academic.myclass'],
                ['Input Nilai', 'academic.scores.index', 'edit', ['scores.input'], 'academic.scores.*'],
                ['Absensi', 'academic.attendance.index', 'clipboard', ['attendance.input'], 'academic.attendance.*'],
                ['Catatan Wali Kelas', 'academic.notes.index', 'pen', ['notes.input'], 'academic.notes.*'],
                ['Leger Nilai', 'academic.recap', 'chart', ['scores.input', 'reports.prepare'], 'academic.recap*'],
            ]],
            ['label' => 'Rapor', 'items' => [
                ['Rapor Kelas', 'reports.index', 'file-check', ['reports.prepare', 'reports.view_all', 'reports.approve'], 'reports.index'],
                ['Titimangsa Rapor', 'reports.settings', 'calendar', ['reports.settings'], 'reports.settings*'],
                ['Arsip Rapor', 'reports.archive', 'archive', ['reports.view_all', 'reports.prepare'], 'reports.archive'],
                ['Rekap & Leger Nilai', 'academic.recap', 'chart', ['scores.view'], 'academic.recap*'],
            ]],
            ['label' => 'Data Induk', 'items' => [
                ['Data Lembaga', 'master.school.edit', 'building', ['school.manage'], 'master.school.*'],
                ['Peserta Didik', 'master.students.index', 'users', ['students.view', 'students.manage'], 'master.students.*'],
                ['Guru / Wali Kelas', 'master.teachers.index', 'teacher', ['teachers.view', 'teachers.manage'], 'master.teachers.*'],
                ['Tahun Ajaran', 'master.years.index', 'calendar', ['years.manage'], 'master.years.*'],
                ['Kurikulum', 'master.curricula.index', 'layers', ['curricula.manage'], 'master.curricula.*'],
                ['Mata Pelajaran', 'master.subjects.index', 'book', ['subjects.manage'], 'master.subjects.*'],
                ['Rombongan Belajar', 'master.classes.index', 'class', ['classes.view', 'classes.manage'], 'master.classes.*'],
                ['Kenaikan Kelas', 'master.promotions.index', 'arrow-up', ['promotions.manage'], 'master.promotions.*'],
                ['Bobot Penilaian', 'academic.weights.edit', 'settings', ['weights.manage'], 'academic.weights.*'],
            ]],
            ['label' => 'Sistem', 'items' => [
                ['Pengguna', 'admin.users.index', 'users', ['users.manage'], 'admin.users.*'],
                ['Role & Hak Akses', 'admin.roles.index', 'shield', ['roles.manage'], 'admin.roles.*'],
                ['Pengaturan Aplikasi', 'admin.settings.edit', 'settings', ['settings.manage'], 'admin.settings.*'],
                ['Backup Database', 'admin.backups.index', 'database', ['backup.manage'], 'admin.backups.*'],
                ['Log Aktivitas', 'admin.logs.index', 'clock', ['logs.view'], 'admin.logs.*'],
            ]],
        ];

        $out = [];
        foreach ($groups as $g) {
            $items = [];
            foreach ($g['items'] as [$label, $route, $icon, $perms, $pattern]) {
                if ($perms === null || $user->hasAnyPermission($perms)) {
                    // Super Admin tidak perlu portal peserta didik / menu wali kelas
                    if ($user->isSuperAdmin() && in_array($route, ['portal.reports', 'academic.myclass', 'academic.scores.index', 'academic.attendance.index', 'academic.notes.index', 'academic.recap'], true) && $g['label'] !== 'Rapor') {
                        continue;
                    }
                    if ($g['label'] === 'Wali Kelas' && $route === 'academic.recap' && $user->hasPermission('scores.view')) {
                        continue;
                    }
                    $items[] = compact('label', 'route', 'icon', 'pattern');
                }
            }
            if ($items) {
                $out[] = ['label' => $g['label'], 'items' => $items];
            }
        }

        return $out;
    }
}
