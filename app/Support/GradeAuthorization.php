<?php

namespace App\Support;

use App\Models\Course;
use App\Models\GradeEvaluationSet;
use App\Models\Representative;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class GradeAuthorization
{
    public static function ensureCanManageCourse(User $user, Course $course): void
    {
        if ($user->role === User::ROLE_ADMIN) {
            return;
        }

        if ($user->role !== User::ROLE_TEACHER || ! $user->hasPermission('grades.manage')) {
            throw new AuthorizationException('No tienes permiso para gestionar evaluaciones.');
        }

        $teacher = Teacher::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', $user->email))
            ->first();

        if (! $teacher || (int) $course->teacher_id !== (int) $teacher->id) {
            throw new AuthorizationException('Solo el profesor titular puede cargar evaluaciones en este curso.');
        }
    }

    public static function ensureCanManageEvaluationSet(User $user, GradeEvaluationSet $set): void
    {
        $set->loadMissing('course');
        static::ensureCanManageCourse($user, $set->course);
    }

    public static function teacherOwnsCourse(User $user, Course $course): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        $teacher = Teacher::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', $user->email))
            ->first();

        return $teacher && (int) $course->teacher_id === (int) $teacher->id;
    }

    public static function representativeCanAccessStudent(User $user, Student $student): bool
    {
        if ($user->role !== User::ROLE_REPRESENTATIVE) {
            return false;
        }

        $representative = Representative::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('user_id')->where('email', $user->email);
                    });
            })
            ->first();

        return $representative && $representative->students()->where('students.id', $student->id)->exists();
    }
}
