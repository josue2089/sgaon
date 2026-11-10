<?php

namespace App\Console\Commands;

use App\Mail\LevelRenewalReminderMail;
use App\Models\AcademicLevel;
use App\Models\Alert;
use App\Models\Course;
use App\Models\Student;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\ProgramLevel;
use App\Services\LevelRenewalService;
use App\Support\CoursePlanner;
use App\Support\RenewalEnrollmentEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class SendLevelRenewalReminders extends Command
{
    private const TEST_REMAINING_SESSIONS_TRIGGER_MAX = 15;

    protected $signature = 'levels:send-renewal-reminders {--dry-run}';

    protected $description = 'Genera recordatorios 5 dias antes de la finalizacion del curso actual para inscripcion al siguiente nivel.';

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $dueStudentIds = [];
        $processed = 0;

        Enrollment::query()
            ->with(['student.representatives', 'group.course.program', 'group.course.programLevel', 'group.course.courseLevel'])
            ->where('status', 'active')
            ->get()
            ->each(function (Enrollment $enrollment) use ($today, &$dueStudentIds, &$processed): void {
                $course = $enrollment->group?->course;
                $student = $enrollment->student;
                $courseLevel = $course?->programLevel ?: $course?->courseLevel;

                if (! $student || ! $course || ! $courseLevel || ! $course->end_date) {
                    return;
                }

                // Extracurriculares: duran el año escolar y no tienen renovación de nivel.
                if ($course->isExtracurricular()) {
                    return;
                }

                $reminderDate = $course->end_date->copy()->subDays((int) ($courseLevel->reminder_days_before ?? 5));
                $remainingSessions = $this->remainingSessions($course, $today);
                $isDueBySessions = $remainingSessions >= 1 && $remainingSessions <= self::TEST_REMAINING_SESSIONS_TRIGGER_MAX;
                if (! $reminderDate->isSameDay($today) && ! $isDueBySessions) {
                    return;
                }

                $nextLevel = $this->resolveNextLevel($course);
                if (! $nextLevel) {
                    return;
                }

                $dueStudentIds[] = $student->id;
                $processed++;
                $nextCourse = $this->ensureNextCourseForLevel($course, $nextLevel);
                $eligibility = RenewalEnrollmentEligibility::evaluateForCourse($student, $course);

                if ($this->option('dry-run')) {
                    $status = $eligibility['eligible'] ? 'elegible' : 'bloqueado';
                    $this->line("DRY RUN: {$student->full_name} -> {$courseLevel->name} / siguiente {$nextLevel->name} ({$status})");

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

                if ($eligibility['eligible']) {
                    $alert->message = "El curso {$course->name} finaliza el {$course->end_date->format('d/m/Y')}. Ya puedes inscribirte al siguiente nivel: {$nextLevel->name}.";
                } else {
                    $alert->message = "El curso {$course->name} finaliza el {$course->end_date->format('d/m/Y')}. Tu inscripción al siguiente nivel está en revisión por resultado Need Support.";
                }
                $alert->resolved_at = null;
                $shouldSendEmail = ! $alert->exists || is_null($alert->emailed_at);
                $alert->save();

                if ($shouldSendEmail && $eligibility['eligible'] && $this->sendReminder($student, $course, $courseLevel, $nextLevel, $nextCourse)) {
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

    /** Al alumno y a sus representantes, cada uno con el enlace de su portal (#442). */
    private function sendReminder(Student $student, Course $course, Model $currentLevel, Model $nextLevel, ?Course $nextCourse): bool
    {
        $representativeEmails = $student->representatives
            ->pluck('email')
            ->filter(fn ($email) => filled($email))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->unique();
        $studentEmail = filled($student->email) ? mb_strtolower(trim((string) $student->email)) : null;

        $mail = fn (bool $forRepresentative) => new LevelRenewalReminderMail(
            student: $student,
            course: $course,
            currentLevel: $currentLevel,
            nextLevel: $nextLevel,
            nextCourse: $nextCourse,
            forRepresentative: $forRepresentative,
        );

        if ($studentEmail && ! $representativeEmails->contains($studentEmail)) {
            Mail::to($studentEmail)->send($mail(false));
        }
        if ($representativeEmails->isNotEmpty()) {
            Mail::to($representativeEmails->all())->send($mail(true));
        }

        return $studentEmail !== null || $representativeEmails->isNotEmpty();
    }

    private function resolveNextLevel(Course $course): ProgramLevel|CourseLevel|null
    {
        if ($course->programLevel) {
            return $course->programLevel->nextLevel();
        }

        return $course->courseLevel?->nextLevel();
    }

    private function remainingSessions(Course $course, CarbonImmutable $today): int
    {
        if (! $course->managed_group_id) {
            return 0;
        }

        return (int) \App\Models\ClassSession::query()
            ->where('group_id', $course->managed_group_id)
            ->whereDate('session_date', '>=', $today->toDateString())
            ->count();
    }

    private function ensureNextCourseForLevel(Course $course, Model $nextLevel): ?Course
    {
        if (! $course->schedule_template_id || ! $course->end_date) {
            return null;
        }

        $nextCourseStartDate = $course->end_date->copy()->addDay()->toDateString();

        // Un curso ya abierto en la ventana (con o sin profesor) sirve: no se crea otro. #442
        $existing = app(LevelRenewalService::class)->nextCourseFor($course, sameScheduleOnly: true);
        if ($existing) {
            if (! $existing->teacher_id) {
                $existing->load(['period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']);
                CoursePlanner::sync($existing);
            }

            return $existing->fresh();
        }

        $draft = new Course([
            'campus_id' => $course->campus_id,
            'academic_level_id' => $this->academicLevelIdFor($course, $nextLevel),
            'program_id' => $nextLevel instanceof ProgramLevel ? $nextLevel->program_id : $course->program_id,
            'program_level_id' => $nextLevel instanceof ProgramLevel ? $nextLevel->id : null,
            'course_level_id' => $nextLevel instanceof CourseLevel ? $nextLevel->id : null,
            'teacher_id' => null,
            'period_id' => $course->period_id,
            'schedule_template_id' => $course->schedule_template_id,
            'start_date' => $nextCourseStartDate,
            'end_date' => null,
            'academic_hours' => (int) (($nextLevel->academic_hours ?? null) ?: $course->academic_hours),
            'status' => 'active',
        ]);
        $draft->setRelation('programLevel', $nextLevel instanceof ProgramLevel ? $nextLevel : null);
        $draft->setRelation('courseLevel', $nextLevel instanceof CourseLevel ? $nextLevel : null);
        $draft->name = $this->nextCourseName($draft);
        $draft->save();
        $draft->load(['period', 'scheduleTemplate', 'managedGroup.sessions.attendanceRecords']);
        CoursePlanner::sync($draft);
        return $draft->fresh();
    }

    /** Si el siguiente nivel es de otro programa, el curso nuevo usa el nivel académico de ese programa. #469 */
    private function academicLevelIdFor(Course $course, Model $nextLevel): ?int
    {
        if (! $nextLevel instanceof ProgramLevel || (int) $nextLevel->program_id === (int) $course->program_id) {
            return $course->academic_level_id;
        }

        return AcademicLevel::firstOrCreate([
            'campus_id' => $course->campus_id,
            'name' => $nextLevel->program->name,
        ])->id;
    }

    private function nextCourseName(Course $draft): string
    {
        $baseName = $draft->buildStructuredName();
        $candidate = $baseName;
        $suffix = 1;
        while (Course::query()->where('campus_id', $draft->campus_id)->where('name', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$baseName} - Cohorte {$suffix}";
        }

        return $candidate;
    }
}
