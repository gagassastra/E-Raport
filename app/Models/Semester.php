<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Semester extends Model
{
    protected $fillable = ['academic_year_id', 'jenis', 'nama', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->with('academicYear')->first();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function reportSetting(): HasOne
    {
        return $this->hasOne(ReportSetting::class);
    }

    public function getLabelAttribute(): string
    {
        return ($this->academicYear?->nama ?? '').' - '.ucfirst($this->jenis);
    }
}
