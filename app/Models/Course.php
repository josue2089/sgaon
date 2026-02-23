<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['campus_id', 'academic_level_id', 'name', 'code', 'description', 'status'];

    public function level()
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
