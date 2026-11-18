<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSetting extends Model
{
    protected $fillable = ['semester_id', 'tempat', 'tanggal_terbit', 'updated_by'];

    protected function casts(): array
    {
        return ['tanggal_terbit' => 'date'];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function getTitimangsaAttribute(): string
    {
        return $this->tempat.', '.$this->tanggal_terbit->locale('id')->translatedFormat('j F Y');
    }
}
