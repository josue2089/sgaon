<?php

namespace App\Models;

use App\Support\CefrScale;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'code',
        'sort_order',
        'level_number',
        'part',
        'cefr_label',
        'gse_min',
        'gse_max',
        'book',
        'next_program_level_id',
        'program_total',
        'academic_hours',
        'base_price_eur',
        'reminder_days_before',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'base_price_eur' => 'float',
            'level_number' => 'integer',
            'gse_min' => 'integer',
            'gse_max' => 'integer',
        ];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function lessons()
    {
        return $this->hasMany(ProgramLevelLesson::class)->orderBy('sort_order');
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function nextProgramLevel()
    {
        return $this->belongsTo(self::class, 'next_program_level_id');
    }

    /** Siguiente nivel: el configurado (puede ser de otro programa, p. ej. Primary 6B → HS1A) o el siguiente del programa. */
    public function nextLevel(): ?self
    {
        if ($this->next_program_level_id) {
            $configured = $this->nextProgramLevel()->where('status', 'active')->first();
            if ($configured) {
                return $configured;
            }
        }

        return self::query()
            ->where('program_id', $this->program_id)
            ->where('status', 'active')
            ->where('sort_order', '>', $this->sort_order)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Subnivel equivalente en otro programa: mismo número de nivel y misma parte (HS4B ↔ IU 4B).
     * Los niveles de High School y de adultos son paralelos. #469
     */
    public function equivalentIn(Program $program): ?self
    {
        if (! $this->level_number) {
            return null;
        }

        return self::query()
            ->where('program_id', $program->id)
            ->where('status', 'active')
            ->where('level_number', $this->level_number)
            ->where(fn ($query) => $this->part ? $query->where('part', $this->part) : $query->whereNull('part'))
            ->orderBy('sort_order')
            ->first();
    }

    /** "CEFR B1 · GSE 43–50", o null si el nivel no tiene equivalencia. */
    public function cefrSummary(): ?string
    {
        return CefrScale::summary($this->cefr_label, $this->gse_min, $this->gse_max);
    }

    public function resolvedBasePriceEur(): ?float
    {
        $levelPrice = (float) ($this->base_price_eur ?? 0);
        if ($levelPrice > 0) {
            return $levelPrice;
        }

        $program = $this->relationLoaded('program') ? $this->program : $this->program()->first();
        $programPrice = (float) ($program?->base_price_eur ?? 0);

        return $programPrice > 0 ? $programPrice : null;
    }
}
