<?php

namespace App\Console\Commands;

use App\Mail\LevelRenewalReminderMail;
use App\Models\Alert;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\ProgramLevel;
use App\Models\ScheduleTemplate;
use App\Support\RenewalEnrollmentEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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
                $remainingSessions = $this->remainingSessions($course, $today);
                $isDueBySessions = $remainingSessions >= 1 && $remainingSessions <= 2;
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

                if ($shouldSendEmail && ! empty($student->email) && $eligibility['eligible']) {
                    Mail::to($student->email)->send(new LevelRenewalReminderMail(
                        student: $student,
                        course: $course,
                        currentLevel: $courseLevel,
                        nextLevel: $nextLevel,
                        nextCourse: $nextCourse,
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
        $query = Course::query()
            ->where('campus_id', $course->campus_id)
            ->where('schedule_template_id', $course->schedule_template_id)
            ->whereDate('start_date', $nextCourseStartDate)
            ->whereNull('teacher_id');

        if ($nextLevel instanceof ProgramLevel) {
            $query->where('program_level_id', $nextLevel->id);
        } else {
            $query->where('course_level_id', $nextLevel->id);
        }

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        $scheduleTemplate = ScheduleTemplate::query()->find($course->schedule_template_id);
        if (! $scheduleTemplate) {
            return null;
        }

        $draft = new Course([
            'campus_id' => $course->campus_id,
            'academic_level_id' => $course->academic_level_id,
            'program_id' => $course->program_id,
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
        $draft->setRelation('scheduleTemplate', $scheduleTemplate);
        $draft->name = $this->nextCourseName($draft);
        $draft->save();

        $group = Group::query()->create([
            'campus_id' => $course->campus_id,
            'course_id' => $draft->id,
            'teacher_id' => null,
            'name' => trim(($course->managedGroup?->name ?: 'Grupo').'-SIG-'.$draft->id),
            'period' => $course->period?->code,
            'schedule' => $course->managedGroup?->schedule ?: $scheduleTemplate->display_label,
            'start_date' => $draft->start_date,
            'end_date' => null,
            'status' => 'active',
            'capacity' => $course->managedGroup?->capacity ?: 30,
        ]);

        $draft->forceFill(['managed_group_id' => $group->id])->save();

        return $draft;
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
