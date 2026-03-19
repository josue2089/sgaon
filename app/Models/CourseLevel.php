<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage',
        'name',
        'code',
        'scale_position',
        'scale_total',
        'cefr_reference',
        'description',
        'status',
        'reminder_days_before',
    ];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function nextLevel(): ?self
    {
        return self::query()
            ->where('status', 'active')
            ->where('scale_position', '>', $this->scale_position)
            ->orderBy('scale_position')
            ->first();
    }
}
