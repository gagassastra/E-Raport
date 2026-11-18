<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends Model
{
    protected $fillable = [
        'student_id', 'class_id', 'academic_year_id', 'semester_id', 'status', 'versi_aktif', 'is_locked',
        'guru_user_id', 'guru_signed_at', 'submitted_at', 'kepala_user_id', 'approved_at', 'catatan_perbaikan',
    ];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean', 'guru_signed_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ReportCardDetail::class)->orderBy('urutan');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class)->orderByDesc('versi');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ReportApproval::class)->orderBy('waktu')->orderBy('id');
    }

    public function verification()
    {
        return $this->hasOne(ReportVerification::class)->where('is_valid', true)->latestOfMany();
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_user_id');
    }

    public function kepala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_user_id');
    }

    public function activeVersion(): ?ReportVersion
    {
        return $this->versions()->where('status', 'berlaku')->first();
    }

    public function getStatusLabelAttribute(): string
    {
        return config("erapor.status_rapor.{$this->status}.0", $this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return config("erapor.status_rapor.{$this->status}.1", 'gray');
    }

    /** Kartu terkunci jika sedang menunggu pengesahan atau sudah disahkan. */
    public function isEditableData(): bool
    {
        return ! in_array($this->status, ['menunggu_pengesahan', 'disahkan'], true);
    }
}
