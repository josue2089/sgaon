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

    public const DAY_SHORT_LABELS = [
        'mon' => 'L',
        'tue' => 'M',
        'wed' => 'M',
        'thu' => 'J',
        'fri' => 'V',
        'sat' => 'S',
        'sun' => 'D',
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


    public function getDaysShortLabelAttribute(): string
    {
        return collect($this->days ?? [])
            ->map(fn (string $day) => self::DAY_SHORT_LABELS[$day] ?? strtoupper(substr($day, 0, 1)))
            ->implode('-');
    }

    public static function formatShortHour(?string $time): string
    {
        if (! $time) {
            return '';
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '00');
        $hours = (int) $hours;
        $displayHours = $hours % 12 ?: 12;

        return $displayHours.'.'.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
    }

    public function getTimeRangeShortLabelAttribute(): string
    {
        return trim(self::formatShortHour($this->starts_at).' '.self::formatShortHour($this->ends_at));
    }

    public function getCompactLabelAttribute(): string
    {
        $days = $this->days_short_label;
        $time = $this->time_range_short_label;

        return trim($days.($days && $time ? ' ' : '').$time);
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
