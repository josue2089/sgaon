<?php

namespace App\Console\Commands;

use App\Mail\DailyPendingMail;
use App\Models\User;
use App\Support\TodayInbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía a cada admin activo el resumen de "Pendientes de hoy" de su sede (el master, de todas).
 * Si no tiene pendientes, no se envía. Ticket DevTeam #346.
 */
class SendDailyPendingSummary extends Command
{
    protected $signature = 'admins:send-daily-pending {--dry-run : Muestra destinatarios y cantidades sin enviar}';

    protected $description = 'Envía a cada admin el resumen diario de pendientes de su sede.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        $admins = User::query()
            ->with('campus')
            ->where('role', User::ROLE_ADMIN)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'inactive'))
            ->whereNotNull('email')
            ->orderBy('id')
            ->get();

        foreach ($admins as $admin) {
            $items = array_values(array_filter(TodayInbox::for($admin), fn (array $item) => $item['count'] > 0));
            if ($items === []) {
                $skipped++;

                continue;
            }

            $scope = $admin->canAccessAllCampuses() ? 'Todas las sedes' : ($admin->campus?->name ?? 'Tu sede');
            $summary = collect($items)->map(fn ($item) => $item['count'].' '.mb_strtolower($item['label']))->implode(', ');
            $this->line(($dryRun ? '[dry-run] ' : '')."{$admin->email} ({$scope}): {$summary}");

            if ($dryRun) {
                $sent++;

                continue;
            }

            try {
                Mail::to($admin->email)->send(new DailyPendingMail($admin, $items, $scope));
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("No se pudo enviar a {$admin->email}: {$exception->getMessage()}");
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Correos: {$sent}. Sin pendientes (no se envía): {$skipped}.");

        return self::SUCCESS;
    }
}
