<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'name',
        'holiday_date',
        'month',
        'day',
        'is_recurring',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_recurring' => 'boolean',
            'month' => 'integer',
            'day' => 'integer',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForCampus(Builder $query, ?int $campusId): Builder
    {
        return $query->where(function (Builder $builder) use ($campusId): void {
            $builder->whereNull('campus_id');
            if ($campusId) {
                $builder->orWhere('campus_id', $campusId);
            }
        });
    }

    public function occursOn(Carbon $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->is_recurring) {
            return (int) $this->month === (int) $date->month
                && (int) $this->day === (int) $date->day;
        }

        return $this->holiday_date?->isSameDay($date) ?? false;
    }

    public function getOccurrenceLabelAttribute(): string
    {
        if ($this->is_recurring) {
            return sprintf('Cada año · %02d/%02d', (int) $this->day, (int) $this->month);
        }

        return $this->holiday_date?->format('d/m/Y') ?? 'Sin fecha';
    }
}
