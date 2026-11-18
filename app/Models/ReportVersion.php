<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReportVersion extends Model
{
    protected $fillable = [
        'report_card_id', 'versi', 'status', 'snapshot', 'pdf_path', 'pdf_hash', 'dibuat_oleh',
        'diterbitkan_at', 'dinyatakan_tidak_berlaku_at', 'alasan_tidak_berlaku',
    ];

    protected function casts(): array
    {
        return ['diterbitkan_at' => 'datetime', 'dinyatakan_tidak_berlaku_at' => 'datetime'];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(ReportVerification::class);
    }

    public function data(): array
    {
        return json_decode($this->snapshot, true) ?: [];
    }
}
