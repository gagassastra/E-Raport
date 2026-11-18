<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'role_id', 'is_active', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function signature(): HasOne
    {
        return $this->hasOne(TeacherSignature::class);
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->role
                ? $this->role->permissions()->pluck('slug')->all()
                : [];
        }

        return in_array($slug, $this->permissionCache, true);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $s) {
            if ($this->hasPermission($s)) {
                return true;
            }
        }

        return false;
    }

    public function getJabatanAttribute(): string
    {
        return match ($this->role?->slug) {
            'kepala_pkbm' => 'Kepala PKBM',
            'guru' => 'Guru / Wali Kelas',
            default => $this->role?->nama ?? '-',
        };
    }

    public function getInisialAttribute(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }
}
