<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramLevelLesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_level_id',
        'class_number',
        'unit',
        'content',
        'notes',
        'sort_order',
    ];

    public function programLevel()
    {
        return $this->belongsTo(ProgramLevel::class);
    }
}
