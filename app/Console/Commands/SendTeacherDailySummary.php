<?php

namespace App\Console\Commands;

use App\Mail\TeacherDailyMail;
use App\Models\ClassSession;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TodayInbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correo de las 7:00 a cada profesor con acceso: sus clases de hoy, las asistencias pendientes y las recuperativas
 * reservadas. Usa el mismo cálculo que la bandeja "Hoy" (TodayInbox) y solo con sus clases. #446
 */
class SendTeacherDailySummary extends Command
{
    protected $signature = 'teachers:send-daily-summary {--dry-run : Muestra destinatarios y cantidades sin enviar}';

    protected $description = 'Envía a cada profesor el resumen diario de sus clases y pendientes.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        $users = User::query()
            ->where('role', User::ROLE_TEACHER)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'inactive'))
            ->whereNotNull('email')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $teacher = Teacher::forUser($user);
            if (! $teacher || $teacher->status === 'inactive') {
                $skipped++;

                continue;
            }

            $todaySessions = Teacher::constrainSessions(ClassSession::query(), $teacher)
                ->with('group.course')
                ->whereDate('session_date', now()->toDateString())
                ->orderBy('starts_at')
                ->get();
            $items = array_values(array_filter(
                TodayInbox::for($user, $teacher),
                fn (array $item) => $item['count'] > 0 && $item['key'] !== 'teacher_makeups'
            ));
            $makeupBookings = TodayInbox::teacherMakeupBookings($teacher)
                ->with(['makeupSession', 'makeupRequest.student'])
                ->get()
                ->sortBy(fn ($booking) => $booking->makeupSession?->session_date?->toDateString().$booking->makeupSession?->starts_at)
                ->values();

            if ($todaySessions->isEmpty() && $items === [] && $makeupBookings->isEmpty()) {
                $skipped++;

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '')."{$user->email}: {$todaySessions->count()} clases hoy, "
                .collect($items)->sum('count').' pendientes, '.$makeupBookings->count().' recuperativas');

            if ($dryRun) {
                $sent++;

                continue;
            }

            try {
                Mail::to($user->email)->send(new TeacherDailyMail($user, $teacher, $todaySessions, $items, $makeupBookings));
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("No se pudo enviar a {$user->email}: {$exception->getMessage()}");
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Correos: {$sent}. Sin nada que avisar o sin ficha: {$skipped}.");

        return self::SUCCESS;
    }
}
