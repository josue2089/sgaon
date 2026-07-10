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
        'makeup_request_id',
        'amount',
        'currency',
        'original_amount',
        'exchange_rate',
        'exchange_rate_effective_at',
        'payment_method_id',
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
            'original_amount' => 'float',
            'exchange_rate' => 'float',
            'exchange_rate_effective_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }

    public function makeupRequest()
    {
        return $this->belongsTo(MakeupRequest::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
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
