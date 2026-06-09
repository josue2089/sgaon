<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CampusScope
{
    public static function campusIdFor(?User $user): ?int
    {
        if (! $user || $user->isMasterAdmin()) {
            return null;
        }

        return $user->campus_id;
    }

    public static function apply(Builder $query, ?User $user, string $column = 'campus_id'): Builder
    {
        $campusId = self::campusIdFor($user);

        if ($campusId) {
            $query->where($column, $campusId);
        }

        return $query;
    }
}
