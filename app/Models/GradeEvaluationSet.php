<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeEvaluationSet extends Model
{
    protected $fillable = [
        'campus_id',
        'course_id',
        'group_id',
        'evaluated_on',
        'title',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_on' => 'date',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'grade_evaluation_set_id');
    }
}
