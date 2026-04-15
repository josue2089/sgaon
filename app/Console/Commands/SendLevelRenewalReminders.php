<?php

namespace App\Console\Commands;

use App\Mail\LevelRenewalReminderMail;
use App\Models\Alert;
use App\Models\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLevelRenewalReminders extends Command
{
    protected $signature = 'levels:send-renewal-reminders {--dry-run}';

    protected $description = 'Genera recordatorios 5 dias antes de la finalizacion del curso actual para inscripcion al siguiente nivel.';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $dueStudentIds = [];
        $processed = 0;

        Enrollment::query()
            ->with(['student', 'group.course.program', 'group.course.programLevel', 'group.course.courseLevel'])
            ->where('status', 'active')
            ->get()
            ->each(function (Enrollment $enrollment) use ($today, &$dueStudentIds, &$processed): void {
                $course = $enrollment->group?->course;
                $student = $enrollment->student;
                $courseLevel = $course?->programLevel ?: $course?->courseLevel;

                if (! $student || ! $course || ! $courseLevel || ! $course->end_date) {
                    return;
                }

                $reminderDate = $course->end_date->copy()->subDays((int) ($courseLevel->reminder_days_before ?? 5));
                if (! $reminderDate->isSameDay($today)) {
                    return;
                }

                $nextLevel = $courseLevel->nextLevel();
                if (! $nextLevel) {
                    return;
                }

                $dueStudentIds[] = $student->id;
                $processed++;

                if ($this->option('dry-run')) {
                    $this->line("DRY RUN: {$student->full_name} -> {$courseLevel->name} / siguiente {$nextLevel->name}");

                    return;
                }

                $alert = Alert::firstOrNew(
                    [
                        'campus_id' => $student->campus_id,
                        'student_id' => $student->id,
                        'type' => 'level_renewal',
                        'status' => 'open',
                    ]
                );

                $alert->message = "El curso {$course->name} finaliza el {$course->end_date->format('d/m/Y')}. Recordar inscripcion al siguiente nivel: {$nextLevel->name}.";
                $alert->resolved_at = null;
                $shouldSendEmail = ! $alert->exists || is_null($alert->emailed_at);
                $alert->save();

                if ($shouldSendEmail && ! empty($student->email)) {
                    Mail::to($student->email)->send(new LevelRenewalReminderMail(
                        student: $student,
                        course: $course,
                        currentLevel: $courseLevel,
                        nextLevel: $nextLevel,
                    ));

                    $alert->forceFill(['emailed_at' => now()])->save();
                }
            });

        if (! $this->option('dry-run')) {
            Alert::query()
                ->where('type', 'level_renewal')
                ->where('status', 'open')
                ->when(! empty($dueStudentIds), fn ($query) => $query->whereNotIn('student_id', array_unique($dueStudentIds)))
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);
        }

        $this->info("Recordatorios procesados: {$processed}");

        return self::SUCCESS;
    }
}
