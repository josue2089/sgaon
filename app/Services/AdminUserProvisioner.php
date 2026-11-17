<?php

namespace App\Services;

use App\Mail\AdminCredentialsMail;
use App\Models\Role;
use App\Models\User;
use App\Support\CampusScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminUserProvisioner
{
    /**
     * @param  array<int>  $campusIds
     * @return array{user: User, plain_password: string}
     */
    public function create(string $name, string $email, ?string $phone, string $status, string $accessMode, array $campusIds): array
    {
        $plainPassword = Str::password(12);

        // #454: usuario, sedes y rol se guardan juntos; el correo (en cola) sale solo si todo se guardó.
        return DB::transaction(function () use ($name, $email, $phone, $status, $accessMode, $campusIds, $plainPassword) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $plainPassword,
                'role' => User::ROLE_ADMIN,
                'is_master' => false,
                'access_all_campuses' => $accessMode === 'all',
                'status' => $status,
                'campus_id' => $accessMode === 'selected' ? ($campusIds[0] ?? null) : null,
            ]);

            if ($accessMode === 'selected') {
                $user->campuses()->sync($campusIds);
                CampusScope::flush();
            } else {
                $user->campuses()->detach();
                CampusScope::flush();
            }

            $adminRole = Role::query()->firstOrCreate(
                ['name' => User::ROLE_ADMIN],
                ['label' => 'Administrador']
            );
            $user->roles()->syncWithoutDetaching([$adminRole->id]);

            Mail::to($user->email)->send(new AdminCredentialsMail($user, $plainPassword));

            return [
                'user' => $user->fresh(['campuses', 'campus']),
                'plain_password' => $plainPassword,
            ];
        });
    }

    public function resendCredentials(User $user): string
    {
        $plainPassword = Str::password(12);
        $user->update(['password' => $plainPassword]);
        Mail::to($user->email)->send(new AdminCredentialsMail($user->fresh(), $plainPassword));

        return $plainPassword;
    }

    /**
     * @param  array<int>  $campusIds
     */
    public function update(User $user, string $name, ?string $phone, string $status, string $accessMode, array $campusIds): User
    {
        return DB::transaction(function () use ($user, $name, $phone, $status, $accessMode, $campusIds) {
            $user->update([
                'name' => $name,
                'phone' => $phone,
                'status' => $status,
                'access_all_campuses' => $accessMode === 'all',
                'campus_id' => $accessMode === 'selected' ? ($campusIds[0] ?? null) : null,
            ]);

            if ($accessMode === 'selected') {
                $user->campuses()->sync($campusIds);
                CampusScope::flush();
            } else {
                $user->campuses()->detach();
                CampusScope::flush();
            }

            return $user->fresh(['campuses', 'campus']);
        });
    }
}
