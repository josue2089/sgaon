<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    /** Programa de adultos "Basic User – Prof. Use" (#469). */
    public const ADULTS_CODE = 'BUPU';

    protected $fillable = [
        'name',
        'code',
        'status',
        'description',
        'base_price_eur',
        'is_extracurricular',
        'audience',
        'age_min',
        'age_max',
        'uses_cefr',
    ];

    protected function casts(): array
    {
        return [
            'base_price_eur' => 'float',
            'is_extracurricular' => 'boolean',
            'uses_cefr' => 'boolean',
            'age_min' => 'integer',
            'age_max' => 'integer',
        ];
    }

    public function levels()
    {
        return $this->hasMany(ProgramLevel::class)->orderBy('sort_order');
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
