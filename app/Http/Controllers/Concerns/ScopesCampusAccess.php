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

    protected function campusId(): ?int
    {
        return CampusScope::campusIdFor($this->campusUser());
    }

    protected function primaryCampusId(): ?int
    {
        return CampusScope::primaryCampusId($this->campusUser());
    }

    protected function singleAllowedCampusId(): ?int
    {
        return CampusScope::singleAllowedCampusId($this->campusUser());
    }

    protected function applyCampusScope(Builder $query, string $column = 'campus_id'): Builder
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
