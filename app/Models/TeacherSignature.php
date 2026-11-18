<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TeacherSignature extends Model
{
    protected $fillable = ['user_id', 'tipe', 'mime', 'file_path', 'hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contents(): ?string
    {
        return Storage::disk('local')->exists($this->file_path) ? Storage::disk('local')->get($this->file_path) : null;
    }

    public function dataUri(): ?string
    {
        $c = $this->contents();
        if (! $c) {
            return null;
        }
        if ($this->mime === 'image/png') {
            $c = \App\Support\PngNormalizer::toRgbPng($c);
        }

        return 'data:'.$this->mime.';base64,'.base64_encode($c);
    }
}
