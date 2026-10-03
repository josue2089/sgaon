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
        'end_date',
        'kind',
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
            'end_date' => 'date',
            'is_recurring' => 'boolean',
            'month' => 'integer',
            'day' => 'integer',
        ];
    }

    public const KIND_HOLIDAY = 'holiday';

    public const KIND_SCHOOL_CLOSURE = 'school_closure';

    public function getKindLabelAttribute(): string
    {
        return $this->kind === self::KIND_SCHOOL_CLOSURE ? 'Día sin clase del colegio' : 'Feriado';
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

        if (! $this->holiday_date) {
            return false;
        }

        if ($this->end_date) {
            return $date->copy()->startOfDay()->betweenIncluded($this->holiday_date->copy()->startOfDay(), $this->end_date->copy()->startOfDay());
        }

        return $this->holiday_date->isSameDay($date);
    }

    public function getOccurrenceLabelAttribute(): string
    {
        if ($this->is_recurring) {
            return sprintf('Cada año · %02d/%02d', (int) $this->day, (int) $this->month);
        }

        if ($this->holiday_date && $this->end_date && ! $this->end_date->isSameDay($this->holiday_date)) {
            return $this->holiday_date->format('d/m/Y').' al '.$this->end_date->format('d/m/Y');
        }

        return $this->holiday_date?->format('d/m/Y') ?? 'Sin fecha';
    }
}
