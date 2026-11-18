<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $table = 'curricula';

    protected $fillable = ['kode', 'nama', 'deskripsi', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
}
