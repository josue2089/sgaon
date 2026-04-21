<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'student_id',
        'group_id',
        'enrolled_at',
        'status',
        'progress',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'progress' => 'float',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function makeupRequests()
    {
        return $this->hasMany(MakeupRequest::class);
    }

    public function gradeEntries()
    {
        return $this->hasMany(GradeEntry::class);
    }
}
