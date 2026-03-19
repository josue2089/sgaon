<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'user_id',
        'first_name',
        'last_name',
        'document_id',
        'birth_date',
        'email',
        'phone',
        'address',
        'status',
        'enrollment_date',
        'profile_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrollment_date' => 'date',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function representatives()
    {
        return $this->belongsToMany(Representative::class, 'student_representative');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
