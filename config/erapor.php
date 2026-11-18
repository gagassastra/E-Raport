<?php

/**
 * Konfigurasi domain E-Raport PKBM AL-QUDWAH.
 * Izin akses (permissions) berikut dimasukkan ke tabel `permissions` oleh RolePermissionSeeder.
 * Hak akses default tiap role dapat diubah oleh Super Admin dari menu "Role & Hak Akses".
 */
return [
    'roles' => [
        'super_admin' => ['nama' => 'Super Admin', 'deskripsi' => 'Mengelola seluruh sistem, pengguna, hak akses, backup, dan pengaturan.'],
        'kepala_pkbm' => ['nama' => 'Kepala PKBM', 'deskripsi' => 'Memeriksa, menyetujui, dan menandatangani rapor serta melihat laporan akademik.'],
        'operator' => ['nama' => 'Operator Sekolah', 'deskripsi' => 'Mengelola data induk, kelas, tahun ajaran, kenaikan kelas, dan administrasi rapor.'],
        'guru' => ['nama' => 'Guru / Wali Kelas', 'deskripsi' => 'Mengelola nilai seluruh mata pelajaran, absensi, dan catatan pada kelas yang diampu.'],
        'siswa' => ['nama' => 'Peserta Didik', 'deskripsi' => 'Melihat profil dan rapor miliknya yang telah disahkan.'],
    ],

    // slug => [nama, kelompok, role default]
    'permissions' => [
        'users.manage' => ['Kelola pengguna', 'Sistem', ['super_admin']],
        'roles.manage' => ['Kelola role & hak akses', 'Sistem', ['super_admin']],
        'settings.manage' => ['Kelola konfigurasi aplikasi', 'Sistem', ['super_admin']],
        'backup.manage' => ['Kelola backup', 'Sistem', ['super_admin']],
        'logs.view' => ['Lihat log aktivitas', 'Sistem', ['super_admin']],

        'school.manage' => ['Kelola data lembaga', 'Data Induk', ['super_admin', 'operator']],
        'students.view' => ['Lihat data peserta didik', 'Data Induk', ['super_admin', 'kepala_pkbm', 'operator']],
        'students.manage' => ['Kelola data peserta didik', 'Data Induk', ['super_admin', 'operator']],
        'teachers.view' => ['Lihat data guru', 'Data Induk', ['super_admin', 'kepala_pkbm', 'operator']],
        'teachers.manage' => ['Kelola data guru', 'Data Induk', ['super_admin', 'operator']],
        'years.manage' => ['Kelola tahun ajaran & semester', 'Data Induk', ['super_admin', 'operator']],
        'curricula.manage' => ['Kelola kurikulum', 'Data Induk', ['super_admin', 'operator']],
        'subjects.manage' => ['Kelola mata pelajaran', 'Data Induk', ['super_admin', 'operator']],
        'classes.view' => ['Lihat rombongan belajar', 'Data Induk', ['super_admin', 'kepala_pkbm', 'operator']],
        'classes.manage' => ['Kelola rombongan belajar', 'Data Induk', ['super_admin', 'operator']],
        'promotions.manage' => ['Kenaikan kelas & kelulusan', 'Data Induk', ['super_admin', 'operator']],

        'scores.view' => ['Lihat rekap nilai seluruh kelas', 'Akademik', ['super_admin', 'kepala_pkbm', 'operator']],
        'scores.input' => ['Input nilai kelas yang diampu', 'Akademik', ['guru']],
        'attendance.input' => ['Input absensi kelas yang diampu', 'Akademik', ['guru']],
        'notes.input' => ['Input catatan wali kelas', 'Akademik', ['guru']],
        'weights.manage' => ['Atur bobot penilaian', 'Akademik', ['super_admin', 'operator']],

        'reports.prepare' => ['Siapkan & ajukan rapor kelas yang diampu', 'Rapor', ['guru']],
        'reports.view_all' => ['Lihat seluruh rapor', 'Rapor', ['super_admin', 'kepala_pkbm', 'operator']],
        'reports.approve' => ['Periksa & sahkan rapor', 'Rapor', ['kepala_pkbm']],
        'reports.reopen' => ['Buka kembali / batalkan rapor', 'Rapor', ['super_admin', 'kepala_pkbm']],
        'reports.settings' => ['Atur titimangsa rapor', 'Rapor', ['super_admin', 'operator', 'kepala_pkbm']],
        'reports.own' => ['Lihat rapor milik sendiri', 'Rapor', ['siswa']],
        'signature.manage' => ['Kelola tanda tangan digital', 'Rapor', ['guru', 'kepala_pkbm']],
    ],

    'agama' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'],

    'status_rapor' => [
        'belum_diisi' => ['Belum Diisi', 'gray'],
        'sedang_dikerjakan' => ['Sedang Dikerjakan', 'blue'],
        'menunggu_pengesahan' => ['Menunggu Pengesahan Kepala PKBM', 'amber'],
        'disahkan' => ['Disahkan', 'green'],
        'ditolak' => ['Ditolak / Perlu Perbaikan', 'red'],
        'dibuka_kembali' => ['Dibuka Kembali', 'purple'],
        'dibatalkan' => ['Dibatalkan', 'dark'],
    ],
];
