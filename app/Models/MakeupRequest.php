<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MakeupRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_APPROVED_FOR_BOOKING = 'approved_for_booking';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_MISSED = 'missed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'campus_id',
        'student_id',
        'enrollment_id',
        'attendance_record_id',
        'class_session_id',
        'charge_id',
        'payment_id',
        'request_type',
        'price',
        'medical_support_required',
        'medical_support_path',
        'status',
        'payment_notes',
        'validated_by',
        'validated_at',
        'rejection_reason',
        'notification_emailed_at',
        'approval_emailed_at',
        'booking_emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'medical_support_required' => 'boolean',
            'validated_at' => 'datetime',
            'notification_emailed_at' => 'datetime',
            'approval_emailed_at' => 'datetime',
            'booking_emailed_at' => 'datetime',
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

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function missedSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function attachments()
    {
        return $this->hasMany(MakeupRequestAttachment::class)->latest();
    }

    public function booking()
    {
        return $this->hasOne(MakeupBooking::class);
    }

    public function getPaymentProofAttribute(): ?MakeupRequestAttachment
    {
        return $this->attachments->firstWhere('category', 'payment_proof');
    }

    public function getMedicalSupportAttachmentAttribute(): ?MakeupRequestAttachment
    {
        return $this->attachments->firstWhere('category', 'medical_support');
    }
}
