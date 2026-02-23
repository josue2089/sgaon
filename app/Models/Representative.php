<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Representative extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'user_id',
        'first_name',
        'last_name',
        'document_id',
        'email',
        'phone',
        'relation',
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_representative');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
