<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'student_id',
        'concept',
        'amount',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'float',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
