<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_STUDENT = 'student';
    public const ROLE_REPRESENTATIVE = 'representative';

    protected $fillable = [
        'campus_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_master',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_master' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        if ($this->role === $role) {
            return true;
        }

        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }

        $legacy = [
            self::ROLE_TEACHER => ['attendance.manage', 'reports.view', 'dashboard.view'],
            self::ROLE_STUDENT => ['portal.student.view', 'dashboard.view'],
            self::ROLE_REPRESENTATIVE => ['portal.representative.view', 'dashboard.view'],
        ];

        if (in_array($permission, $legacy[$this->role] ?? [], true)) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isMasterAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN && $this->is_master;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
