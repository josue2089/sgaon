<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
        'description',
        'base_price_eur',
        'is_extracurricular',
    ];

    protected function casts(): array
    {
        return [
            'base_price_eur' => 'float',
            'is_extracurricular' => 'boolean',
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
