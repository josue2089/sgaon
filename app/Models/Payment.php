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
        'paid_at_datetime',
        'method',
        'reference',
        'status',
        'received_by',
        'notes',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'paid_at_datetime' => 'datetime',
            'amount' => 'float',
            'voided_at' => 'datetime',
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

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
