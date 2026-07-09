<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class StudentSearch
{
    public static function applyTerm(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($term) {
            $builder
                ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('document_id', 'like', "%{$term}%")
                ->orWhereHas('representatives', fn (Builder $rep) => $rep
                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                    ->orWhere('email', 'like', "%{$term}%"));
        });
    }

    public static function haystack(Student $student): string
    {
        $parts = [
            $student->full_name,
            $student->email,
            $student->document_id,
        ];

        if ($student->relationLoaded('representatives')) {
            foreach ($student->representatives as $representative) {
                $parts[] = $representative->full_name;
                $parts[] = $representative->email;
            }
        }

        return mb_strtolower(implode(' ', array_filter($parts, fn ($part) => filled($part))));
    }

    public static function enrollmentHaystack(Enrollment $enrollment): string
    {
        $parts = [];

        if ($enrollment->student) {
            $parts[] = self::haystack($enrollment->student);
        }

        $parts[] = $enrollment->group?->course?->name;
        $parts[] = $enrollment->group?->name;

        return mb_strtolower(implode(' ', array_filter($parts, fn ($part) => filled($part))));
    }
}
