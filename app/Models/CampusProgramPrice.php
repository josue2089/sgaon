<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampusProgramPrice extends Model
{
    protected $fillable = [
        'campus_id',
        'program_id',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
