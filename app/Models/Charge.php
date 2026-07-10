<?php

namespace App\Models;

use App\Support\PaymentCurrencyConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'student_id',
        'enrollment_id',
        'makeup_request_id',
        'course_id',
        'group_id',
        'period_id',
        'concept',
        'charge_type',
        'billing_period_label',
        'origin',
        'amount',
        'currency',
        'due_date',
        'status',
        'notes',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'float',
            'voided_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
        ];
    }

    public function currencyCode(): string
    {
        return strtoupper((string) ($this->currency ?: PaymentCurrencyConverter::CURRENCY_USD));
    }

    public function isEur(): bool
    {
        return $this->currencyCode() === PaymentCurrencyConverter::CURRENCY_EUR;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function makeupRequest()
    {
        return $this->belongsTo(MakeupRequest::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentRequests()
    {
        return $this->hasMany(ChargePaymentRequest::class)->latest();
    }
}
