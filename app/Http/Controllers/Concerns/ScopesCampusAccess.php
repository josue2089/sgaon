<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Campus;
use App\Models\User;
use App\Support\CampusScope;
use Illuminate\Database\Eloquent\Builder;

trait ScopesCampusAccess
{
    protected function campusUser(): ?User
    {
        return request()->user();
    }

    /** El usuario está limitado a ciertas sedes (no ve todas). */
    protected function campusScoped(): bool
    {
        return CampusScope::allowedCampusIds($this->campusUser()) !== null;
    }

    protected function canAccessCampus(?int $campusId): bool
    {
        return CampusScope::userCanAccessCampus($this->campusUser(), $campusId);
    }

    /**
     * Sede para guardar un registro: con una sola sede se fuerza esa; con varias, la pedida debe estar
     * entre las permitidas. Null = no cambiar (usuario sin límite que no envió sede). #439
     */
    protected function writableCampusId(int|string|null $requested, ?int $fallback = null): ?int
    {
        $requested = $requested !== null && $requested !== '' ? (int) $requested : null;
        $allowed = CampusScope::allowedCampusIds($this->campusUser());

        if ($allowed === null) {
            return $requested ?? $fallback;
        }
        if (count($allowed) === 1) {
            return $allowed[0];
        }

        $campusId = $requested ?? $fallback;
        if ($campusId === null || ! in_array($campusId, $allowed, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['campus_id' => 'Selecciona una de tus sedes.']);
        }

        return $campusId;
    }

    protected function primaryCampusId(): ?int
    {
        return CampusScope::primaryCampusId($this->campusUser());
    }

    protected function singleAllowedCampusId(): ?int
    {
        return CampusScope::singleAllowedCampusId($this->campusUser());
    }

    protected function applyCampusScope(mixed $query, string $column = 'campus_id'): mixed
    {
        return CampusScope::apply($query, $this->campusUser(), $column);
    }

    protected function authorizeCampus(?int $campusId): void
    {
        if (! CampusScope::userCanAccessCampus($this->campusUser(), $campusId)) {
            abort(403);
        }
    }

    protected function allowedCampusesQuery(): Builder
    {
        $query = Campus::query()->orderBy('name');
        $allowed = CampusScope::allowedCampusIds($this->campusUser());

        if ($allowed === null) {
            return $query;
        }

        if ($allowed === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $allowed);
    }
}
