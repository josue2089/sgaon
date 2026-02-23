<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'student_id',
        'charge_id',
        'amount',
        'paid_at',
        'method',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'amount' => 'float',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }
}
