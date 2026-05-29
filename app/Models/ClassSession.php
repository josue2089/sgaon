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
        'program_level_lesson_id',
        'planned_class_number',
        'planned_class_label',
        'planned_unit',
        'planned_content',
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

    public function plannedLesson()
    {
        return $this->belongsTo(ProgramLevelLesson::class, 'program_level_lesson_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function makeupRequests()
    {
        return $this->hasMany(MakeupRequest::class);
    }

    public function canRecordAttendance(?\Carbon\CarbonInterface $reference = null): bool
    {
        if (! $this->session_date) {
            return false;
        }

        $reference ??= now();

        return $this->session_date->startOfDay()->lte($reference->copy()->startOfDay());
    }
}
