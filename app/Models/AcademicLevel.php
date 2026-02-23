<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicLevel extends Model
{
    use HasFactory;

    protected $fillable = ['campus_id', 'name', 'code', 'sort_order', 'description'];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
