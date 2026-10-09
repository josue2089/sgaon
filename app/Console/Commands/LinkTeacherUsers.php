<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vincula fichas de profesor con su usuario de acceso por user_id y corrige casos puntuales
 * (usuario creado como admin que debe ser profesor, usuarios viejos duplicados). Ticket DevTeam #258.
 */
class LinkTeacherUsers extends Command
{
    protected $signature = 'teachers:link-users
        {--dry-run : Solo muestra los cambios, sin aplicarlos}
        {--as-teacher=* : Email de un usuario que debe quedar con rol profesor}
        {--deactivate=* : Email de un usuario que debe quedar desactivado}';

    protected $description = 'Vincula profesores activos con su usuario por email y aplica correcciones de rol/estado indicadas.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';
        $changes = 0;

        $run = function (callable $callback) use ($dryRun) {
            if (! $dryRun) {
                $callback();
            }
        };

        DB::transaction(function () use ($run, $prefix, &$changes) {
            foreach ((array) $this->option('as-teacher') as $email) {
                $user = $this->userByEmail($email);
                if (! $user || $user->is_master) {
                    $this->warn("No se cambia el rol de {$email}: ".(! $user ? 'no existe.' : 'es master.'));

                    continue;
                }
                if ($user->role !== User::ROLE_TEACHER) {
                    $this->line("{$prefix}Rol {$user->role} → teacher: {$user->email} (#{$user->id})");
                    $run(function () use ($user) {
                        $user->roles()->detach();
                        $user->forceFill(['role' => User::ROLE_TEACHER, 'access_all_campuses' => false])->save();
                    });
                    $changes++;
                }
            }

            foreach ((array) $this->option('deactivate') as $email) {
                $user = $this->userByEmail($email);
                if (! $user || $user->is_master) {
                    $this->warn("No se desactiva {$email}: ".(! $user ? 'no existe.' : 'es master.'));

                    continue;
                }
                if ($user->status !== 'inactive') {
                    $this->line("{$prefix}Usuario desactivado: {$user->email} (#{$user->id})");
                    $run(fn () => $user->forceFill(['status' => 'inactive'])->save());
                    $changes++;
                }
            }

            $teachers = Teacher::query()
                ->whereNull('user_id')
                ->whereNotNull('email')
                ->where('status', '!=', 'inactive')
                ->orderBy('id')
                ->get();

            foreach ($teachers as $teacher) {
                $user = $this->userByEmail((string) $teacher->email);
                if (! $user) {
                    continue;
                }
                $willBeTeacher = $user->role === User::ROLE_TEACHER
                    || in_array(strtolower($user->email), array_map('strtolower', (array) $this->option('as-teacher')), true);
                if (! $willBeTeacher) {
                    $this->warn("Ficha #{$teacher->id} {$teacher->full_name}: su email pertenece a un usuario con rol {$user->role}; no se vincula.");

                    continue;
                }
                $this->line("{$prefix}Ficha #{$teacher->id} {$teacher->full_name} → usuario {$user->email} (#{$user->id})");
                $run(fn () => $teacher->forceFill(['user_id' => $user->id])->save());
                $changes++;
            }
        });

        $this->info("{$prefix}Cambios: {$changes}");

        return self::SUCCESS;
    }

    private function userByEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
    }
}
