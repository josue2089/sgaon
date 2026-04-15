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
    ];

    public function levels()
    {
        return $this->hasMany(ProgramLevel::class)->orderBy('sort_order');
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
