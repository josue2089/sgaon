<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'code',
        'description',
        'status',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
