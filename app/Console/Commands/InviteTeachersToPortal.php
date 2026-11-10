<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Services\PortalAccessService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Invita a la plataforma a los profesores activos con correo y sin acceso. #445 */
class InviteTeachersToPortal extends Command
{
    protected $signature = 'teachers:invite-portal
        {--campus= : ID de la sede}
        {--dry-run : Muestra a quién se invitaría sin enviar nada}';

    protected $description = 'Invita a los profesores activos con correo que aún no tienen acceso';

    public function handle(PortalAccessService $access): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $teachers = Teacher::query()
            ->where('status', '!=', 'inactive')
            ->whereNull('user_id')
            ->when($this->option('campus'), fn ($q, $campus) => $q->where('campus_id', $campus))
            ->orderBy('id')
            ->get();

        $invited = 0;
        $skipped = [];
        foreach ($teachers as $teacher) {
            $summary = $access->teacherSummary($teacher);
            if (! $summary['can_invite']) {
                $skipped[] = [$teacher->id, $teacher->full_name, $summary['blocker']];

                continue;
            }
            if (! $dryRun) {
                try {
                    $access->inviteTeacher($teacher);
                } catch (ValidationException $exception) {
                    $skipped[] = [$teacher->id, $teacher->full_name, collect($exception->errors())->flatten()->first()];

                    continue;
                }
            }
            $invited++;
        }

        if ($skipped !== []) {
            $this->table(['ID', 'Profesor', 'No se invita porque'], $skipped);
        }
        $this->info(($dryRun ? '[dry-run] Se invitaría a ' : 'Profesores invitados: ').$invited.'.');

        return self::SUCCESS;
    }
}
