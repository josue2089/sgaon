<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CampusScope
{
    /**
     * @return null All campuses (no filter)
     * @return array<int> Specific campus IDs; empty = no access
     */
    public static function allowedCampusIds(?User $user): ?array
    {
        if (! $user || $user->canAccessAllCampuses()) {
            return null;
        }

        $pivotIds = $user->relationLoaded('campuses')
            ? $user->campuses->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $user->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all();

        if ($pivotIds !== []) {
            return array_values(array_unique($pivotIds));
        }

        if ($user->campus_id) {
            return [(int) $user->campus_id];
        }

        return [];
    }

    /**
     * @deprecated Prefer allowedCampusIds() + apply(). Returns single campus id for legacy callers, or null when all/multiple.
     */
    public static function campusIdFor(?User $user): ?int
    {
        $allowed = self::allowedCampusIds($user);

        if ($allowed === null || count($allowed) !== 1) {
            return null;
        }

        return $allowed[0];
    }

    public static function userCanAccessCampus(?User $user, ?int $campusId): bool
    {
        if (! $campusId) {
            return true;
        }

        $allowed = self::allowedCampusIds($user);

        if ($allowed === null) {
            return true;
        }

        return in_array((int) $campusId, $allowed, true);
    }

    public static function singleAllowedCampusId(?User $user): ?int
    {
        $allowed = self::allowedCampusIds($user);

        if (! is_array($allowed) || count($allowed) !== 1) {
            return null;
        }

        return $allowed[0];
    }

    public static function primaryCampusId(?User $user): ?int
    {
        $allowed = self::allowedCampusIds($user);

        if ($allowed === null) {
            return $user?->campus_id ? (int) $user->campus_id : null;
        }

        return $allowed[0] ?? null;
    }

    public static function apply(Builder $query, ?User $user, string $column = 'campus_id'): Builder
    {
        $allowed = self::allowedCampusIds($user);

        if ($allowed === null) {
            return $query;
        }

        if ($allowed === []) {
            return $query->whereRaw('1 = 0');
        }

        if (count($allowed) === 1) {
            return $query->where($column, $allowed[0]);
        }

        return $query->whereIn($column, $allowed);
    }
}
