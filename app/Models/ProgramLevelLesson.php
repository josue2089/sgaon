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
        'lesson_type',
        'activity_tags',
        'from_template',
    ];

    protected function casts(): array
    {
        return [
            'activity_tags' => 'array',
            'from_template' => 'boolean',
        ];
    }

    /** Clase de relleno creada por el sistema ("Unidad N" / "Contenido base de la clase N"). #470 */
    public function isPlaceholder(): bool
    {
        return (bool) preg_match('/^Unidad \d+$/', (string) $this->unit)
            && (bool) preg_match('/^Contenido base de la clase \d+$/', (string) $this->content);
    }

    public function typeLabel(): ?string
    {
        return \App\Support\LessonTemplates::TYPES[$this->lesson_type] ?? null;
    }

    public function programLevel()
    {
        return $this->belongsTo(ProgramLevel::class);
    }
}
