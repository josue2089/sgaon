<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeEntry extends Model
{
    protected $fillable = [
        'grade_evaluation_set_id',
        'campus_id',
        'enrollment_id',
        'vocabulary_rating',
        'listening_rating',
        'speaking_rating',
        'writing_rating',
        'grammar_rating',
        'observations',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function evaluationSet(): BelongsTo
    {
        return $this->belongsTo(GradeEvaluationSet::class, 'grade_evaluation_set_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function ratingForSkill(string $skill): string
    {
        $column = \App\Support\GradeRubric::skillToColumnMap()[$skill] ?? null;
        if (! $column || ! array_key_exists($column, $this->getAttributes())) {
            return '';
        }

        return (string) $this->getAttribute($column);
    }
}
