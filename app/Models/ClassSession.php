<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'group_id',
        'sequence',
        'session_date',
        'starts_at',
        'ends_at',
        'topic',
        'program_status',
        'program_notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
