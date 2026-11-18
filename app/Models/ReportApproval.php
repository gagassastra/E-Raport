<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportApproval extends Model
{
    public $timestamps = false;

    public const AKSI = [
        'tanda_tangan_guru' => 'Tanda tangan Guru/Wali Kelas',
        'diajukan' => 'Diajukan ke Kepala PKBM',
        'disahkan' => 'Disahkan Kepala PKBM',
        'ditolak' => 'Ditolak / Perlu Perbaikan',
        'dibuka_kembali' => 'Dibuka kembali',
        'dibatalkan' => 'Dibatalkan',
    ];

    protected $fillable = ['report_card_id', 'versi', 'aksi', 'user_id', 'nama_penanda', 'jabatan', 'signature_hash', 'catatan', 'ip', 'waktu'];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
