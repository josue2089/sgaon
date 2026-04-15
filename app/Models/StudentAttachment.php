<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'category',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getFileSizeLabelAttribute(): string
    {
        $size = (int) ($this->file_size ?? 0);
        if ($size <= 0) {
            return 'N/D';
        }
        if ($size >= 1048576) {
            return number_format($size / 1048576, 2).' MB';
        }
        if ($size >= 1024) {
            return number_format($size / 1024, 1).' KB';
        }

        return $size.' B';
    }
}
