<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'academic_level_id',
        'program_id',
        'program_level_id',
        'course_level_id',
        'teacher_id',
        'period_id',
        'schedule_template_id',
        'managed_group_id',
        'name',
        'code',
        'description',
        'start_date',
        'end_date',
        'academic_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'academic_hours' => 'integer',
        ];
    }


    public function buildStructuredName(): string
    {
        $levelName = trim((string) ($this->programLevel?->name ?? $this->courseLevel?->name ?? $this->name));
        $scheduleLabel = $this->scheduleTemplate?->compact_label;
        $teacherLastName = trim((string) ($this->teacher?->last_name ?? ''));
        $teacherLabel = $teacherLastName !== ''
            ? 'Teacher.'.Str::title(Str::lower($teacherLastName))
            : 'Teacher.'.Str::title(Str::lower(trim((string) ($this->teacher?->full_name ?? 'SinAsignar'))));

        $segments = array_filter([
            $levelName,
            $scheduleLabel ? 'Horario: '.$scheduleLabel : null,
            $teacherLabel,
        ]);

        return implode(' - ', $segments);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->buildStructuredName();
    }

    public function level()
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function programLevel()
    {
        return $this->belongsTo(ProgramLevel::class);
    }

    public function courseLevel()
    {
        return $this->belongsTo(CourseLevel::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function scheduleTemplate()
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function managedGroup()
    {
        return $this->belongsTo(Group::class, 'managed_group_id');
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function classSessions()
    {
        return $this->hasManyThrough(ClassSession::class, Group::class);
    }

    public function enrollments()
    {
        return $this->hasManyThrough(Enrollment::class, Group::class);
    }
}
