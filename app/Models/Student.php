<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'user_id',
        'registration_program_id',
        'contract_number',
        'first_name',
        'last_name',
        'document_id',
        'birth_date',
        'email',
        'phone',
        'landline_phone',
        'mobile_phone',
        'address',
        'family_in_institution',
        'family_in_institution_details',
        'status',
        'enrollment_date',
        'profile_photo_path',
        'medical_has_allergies',
        'medical_allergy_details',
        'medical_has_treatment',
        'medical_treatment_details',
        'medical_fever_medication',
        'medical_headache_medication',
        'medical_notes',
        'salesperson',
        'promotion',
        'payment_method',
        'installments',
        'commercial_notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrollment_date' => 'date',
            'family_in_institution' => 'boolean',
            'medical_has_allergies' => 'boolean',
            'medical_has_treatment' => 'boolean',
            'installments' => 'integer',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function registrationProgram()
    {
        return $this->belongsTo(Program::class, 'registration_program_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function representatives()
    {
        return $this->belongsToMany(Representative::class, 'student_representative');
    }

    public function authorizedContacts()
    {
        return $this->hasMany(AuthorizedContact::class)->orderBy('slot');
    }

    public function attachments()
    {
        return $this->hasMany(StudentAttachment::class)->latest();
    }

    public function makeupRequests()
    {
        return $this->hasMany(MakeupRequest::class)->latest();
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function chargePaymentRequests()
    {
        return $this->hasMany(ChargePaymentRequest::class)->latest();
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }
}
