<?php

namespace App\Models;

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

    public function nextLevel(): ?self
    {
        return self::query()
            ->where('program_id', $this->program_id)
            ->where('status', 'active')
            ->where('sort_order', '>', $this->sort_order)
            ->orderBy('sort_order')
            ->first();
    }
}
