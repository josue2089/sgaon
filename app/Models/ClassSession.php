<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'group_id',
        'session_date',
        'starts_at',
        'ends_at',
        'topic',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
