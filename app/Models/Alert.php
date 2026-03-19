<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'student_id',
        'type',
        'status',
        'message',
        'resolved_at',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
