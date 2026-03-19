<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleTemplate extends Model
{
    use HasFactory;

    public const DAY_LABELS = [
        'mon' => 'Lunes',
        'tue' => 'Martes',
        'wed' => 'Miércoles',
        'thu' => 'Jueves',
        'fri' => 'Viernes',
        'sat' => 'Sábado',
        'sun' => 'Domingo',
    ];

    protected $fillable = [
        'campus_id',
        'days',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function getDaysLabelAttribute(): string
    {
        return collect($this->days ?? [])
            ->map(fn (string $day) => self::DAY_LABELS[$day] ?? strtoupper($day))
            ->implode(' · ');
    }

    public function getTimeRangeLabelAttribute(): string
    {
        return trim(($this->starts_at ?? '').' - '.($this->ends_at ?? ''));
    }

    public function getDisplayLabelAttribute(): string
    {
        $days = $this->days_label;
        $time = $this->time_range_label;

        return trim($days.($days && $time ? ' · ' : '').$time);
    }
}
