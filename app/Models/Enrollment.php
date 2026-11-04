<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Retirar o eliminar una inscripción anula sus cuotas extracurriculares futuras sin pagos.
        static::updated(function (Enrollment $enrollment): void {
            if ($enrollment->wasChanged('status') && $enrollment->getOriginal('status') === 'active' && $enrollment->status !== 'active') {
                app(\App\Services\EnrollmentBillingService::class)->voidFutureMonthlyCharges($enrollment, 'Inscripción retirada');
            }
        });

        static::deleting(function (Enrollment $enrollment): void {
            app(\App\Services\EnrollmentBillingService::class)->voidFutureMonthlyCharges($enrollment, 'Inscripción eliminada');
        });
    }

    protected $fillable = [
        'campus_id',
        'student_id',
        'group_id',
        'enrolled_at',
        'status',
        'progress',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'progress' => 'float',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function makeupRequests()
    {
        return $this->hasMany(MakeupRequest::class);
    }

    public function gradeEntries()
    {
        return $this->hasMany(GradeEntry::class);
    }

    /**
     * Borra la inscripción si no tiene historial. Con asistencia o cargos solo la marca como retirada,
     * con una nota. Devuelve true si se borró. #439
     */
    public function deleteOrWithdraw(string $reason): bool
    {
        $this->loadCount(['attendanceRecords', 'charges']);

        if ($this->attendance_records_count > 0 || $this->charges_count > 0) {
            $notes = trim((string) $this->notes);
            $auditNote = $reason.' el '.now()->format('d/m/Y H:i');
            $this->update([
                'status' => 'withdrawn',
                'notes' => $notes === '' ? $auditNote : $notes."\n".$auditNote,
            ]);

            return false;
        }

        $this->delete();

        return true;
    }
}
