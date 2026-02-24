<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'course_id',
        'teacher_id',
        'name',
        'period',
        'schedule',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
