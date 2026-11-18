<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVerification extends Model
{
    protected $fillable = ['report_version_id', 'report_card_id', 'token', 'is_valid', 'revoked_at', 'jumlah_dicek', 'terakhir_dicek_at'];

    protected function casts(): array
    {
        return ['is_valid' => 'boolean', 'revoked_at' => 'datetime', 'terakhir_dicek_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'report_version_id');
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
