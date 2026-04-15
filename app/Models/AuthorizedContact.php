<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizedContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'slot',
        'first_name',
        'last_name',
        'document_id',
        'address',
        'home_phone',
        'mobile_phone',
        'relationship',
        'work_place',
        'work_address',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
