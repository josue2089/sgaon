<?php

namespace App\Console\Commands;

use App\Services\PortalAccessService;
use App\Support\PortalAccessDirectory;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** #472: invitación masiva al portal. Por defecto no reenvía a quien ya tiene una invitación vigente. */
class InvitePortalFamilies extends Command
{
    protected $signature = 'portal:invite
        {--campus= : ID de la sede}
        {--program= : ID del programa}
        {--dry-run : Muestra a cuántos se invitaría sin enviar nada}
        {--resend : También reenvía a quienes ya tienen una invitación vigente}
        {--force : No pide confirmación}';

    protected $description = 'Invita al portal a los representantes y alumnos de una sede o programa';

    public function handle(PortalAccessService $access, PortalAccessDirectory $directory): int
    {
        $campus = $this->option('campus');
        $program = $this->option('program');
        if (! $campus && ! $program) {
            $this->error('Indica --campus o --program.');

            return self::INVALID;
        }

        $students = $directory->students(['campus_id' => $campus, 'program_id' => $program])->get();
        $people = collect($access->peopleFor($students));
        $targets = $people
            ->filter(fn (array $row) => $access->canInviteInBulk($row))
            ->reject(fn (array $row) => $row['state'] === PortalAccessService::STATE_INVITED && ! $this->option('resend'));
        $blocked = $people
            ->where('can_invite', false)
            ->reject(fn (array $row) => $row['blocker'] === PortalAccessService::BLOCKER_KIDS)
            ->groupBy('blocker')
            ->map->count();

        $this->info("Alumnos activos: {$students->count()}. Personas a invitar: {$targets->count()}.");
        foreach ($blocked as $reason => $count) {
            $this->warn("{$count} no se pueden invitar: {$reason}");
        }

        if ($this->option('dry-run')) {
            $this->info('[dry-run] No se envió nada.');

            return self::SUCCESS;
        }
        if ($targets->isEmpty()) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("¿Enviar {$targets->count()} invitaciones reales?")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $byId = fn (string $kind) => $kind === 'student'
            ? $students->keyBy('id')
            : $students->flatMap->representatives->keyBy('id');
        $sent = 0;
        foreach ($targets as $row) {
            try {
                $profile = $byId($row['kind'])[$row['id']];
                $row['kind'] === 'student' ? $access->inviteStudent($profile) : $access->inviteRepresentative($profile);
                $sent++;
            } catch (ValidationException $exception) {
                $this->warn("{$row['key']}: ".collect($exception->errors())->flatten()->first());
            }
        }
        $this->info("Invitaciones enviadas: {$sent}.");

        return self::SUCCESS;
    }
}
