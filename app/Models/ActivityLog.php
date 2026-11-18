<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'user_nama', 'aksi', 'modul', 'subject_type', 'subject_id', 'deskripsi', 'properti', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['properti' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
