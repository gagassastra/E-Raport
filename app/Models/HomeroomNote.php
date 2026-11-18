<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeroomNote extends Model
{
    public const FIELDS = [
        'perkembangan' => 'Perkembangan Peserta Didik',
        'sikap' => 'Sikap dan Kedisiplinan',
        'motivasi' => 'Motivasi Belajar',
        'saran' => 'Saran dan Arahan',
        'catatan_orang_tua' => 'Catatan untuk Orang Tua/Wali',
    ];

    protected $fillable = ['student_id', 'class_id', 'academic_year_id', 'semester_id', 'perkembangan', 'sikap', 'motivasi', 'saran', 'catatan_orang_tua', 'updated_by'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isFilled(): bool
    {
        foreach (array_keys(self::FIELDS) as $f) {
            if (filled($this->{$f})) {
                return true;
            }
        }

        return false;
    }

    public function ringkasan(): string
    {
        $parts = [];
        foreach (self::FIELDS as $f => $label) {
            if (filled($this->{$f})) {
                $parts[$label] = trim($this->{$f});
            }
        }

        return collect($parts)->map(fn ($v, $k) => "$k: $v")->implode("\n");
    }
}
