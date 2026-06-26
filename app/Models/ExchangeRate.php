<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency',
        'rate',
        'effective_at',
        'captured_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'effective_at' => 'datetime',
            'captured_at' => 'datetime',
        ];
    }
}
