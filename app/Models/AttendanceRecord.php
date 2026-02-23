<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_JUSTIFIED = 'justified';

    protected $fillable = ['class_session_id', 'enrollment_id', 'status', 'notes'];

    public function classSession()
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
