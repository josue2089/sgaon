<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MakeupSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'teacher_id',
        'program_id',
        'program_level_id',
        'schedule_template_id',
        'session_date',
        'starts_at',
        'ends_at',
        'capacity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'capacity' => 'integer',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function programLevel()
    {
        return $this->belongsTo(ProgramLevel::class);
    }

    public function scheduleTemplate()
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function bookings()
    {
        return $this->hasMany(MakeupBooking::class);
    }

    public function activeBookings()
    {
        return $this->hasMany(MakeupBooking::class)->whereIn('status', ['reserved', 'attended']);
    }

    public function getBookedCountAttribute(): int
    {
        return (int) ($this->active_bookings_count ?? $this->activeBookings()->count());
    }

    public function getAvailableSlotsAttribute(): int
    {
        return max(0, (int) $this->capacity - $this->booked_count);
    }

    public function getDisplayLabelAttribute(): string
    {
        return trim(($this->session_date?->format('d/m/Y') ?? '').' · '.$this->starts_at.'-'.$this->ends_at);
    }
}
