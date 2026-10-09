<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'user_id',
        'first_name',
        'last_name',
        'document_id',
        'email',
        'phone',
        'status',
        'profile_photo_path',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function makeupSessions()
    {
        return $this->hasMany(MakeupSession::class);
    }

    /**
     * Ficha de profesor del usuario: primero la vinculada por user_id y activa, luego la activa con su email
     * (y la vincula), y por último cualquier vinculada. Así un cambio de email o una ficha vieja no rompen el acceso.
     */
    public static function forUser(?User $user): ?self
    {
        if (! $user) {
            return null;
        }

        $email = strtolower(trim((string) $user->email));
        $candidates = static::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)
                ->when($email !== '', fn ($query) => $query->orWhereRaw('LOWER(email) = ?', [$email])))
            ->orderBy('id')
            ->get();

        $isActive = fn (self $teacher) => $teacher->status !== 'inactive';
        $linked = $candidates->filter(fn (self $teacher) => (int) $teacher->user_id === (int) $user->id);
        $byEmail = $candidates->filter(fn (self $teacher) => ! $teacher->user_id && strtolower(trim((string) $teacher->email)) === $email);

        if ($teacher = $linked->first($isActive)) {
            return $teacher;
        }

        if ($teacher = $byEmail->first($isActive)) {
            $teacher->forceFill(['user_id' => $user->id])->save();

            return $teacher;
        }

        return $linked->first() ?? $byEmail->first();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
