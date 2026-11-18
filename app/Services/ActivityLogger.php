<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(string $aksi, string $modul, string $deskripsi, ?Model $subject = null, array $properti = []): ActivityLog
    {
        $user = Auth::user();
        $req = request();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_nama' => $user?->name,
            'aksi' => $aksi,
            'modul' => $modul,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'deskripsi' => $deskripsi,
            'properti' => $properti ?: null,
            'ip' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 250) : null,
        ]);
    }
}
