<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargePaymentRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'campus_id',
        'student_id',
        'charge_id',
        'representative_id',
        'validated_by',
        'amount',
        'currency',
        'original_amount',
        'exchange_rate',
        'exchange_rate_effective_at',
        'payment_method_id',
        'payment_method',
        'reference',
        'notes',
        'proof_path',
        'proof_original_name',
        'proof_mime_type',
        'proof_file_size',
        'status',
        'submitted_at',
        'validated_at',
        'rejection_reason',
        'approved_emailed_at',
        'rejected_emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'original_amount' => 'float',
            'exchange_rate' => 'float',
            'exchange_rate_effective_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'approved_emailed_at' => 'datetime',
            'rejected_emailed_at' => 'datetime',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
