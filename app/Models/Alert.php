<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    public const TYPE_LABELS = [
        'finance' => 'Pagos pendientes',
        'attendance' => 'Inasistencias',
        'level_renewal' => 'Renovación de nivel',
        'makeup_recovery' => 'Clase recuperativa',
        'payment' => 'Pago',
        'charge' => 'Cargo',
        'overdue' => 'Pago vencido',
    ];

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

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
