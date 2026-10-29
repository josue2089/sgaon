<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos con datos personales (comprobantes, reposos, adjuntos del alumno, exportaciones).
 * Se guardan en el disco privado y solo se sirven por controladores que verifican el acceso. #438
 *
 * Los archivos subidos antes del #438 siguen en el disco público hasta que se corra
 * `files:move-private`; mientras tanto se leen de ahí como respaldo.
 */
final class PrivateFiles
{
    public const DISK = 'local';

    public const LEGACY_DISK = 'public';

    /** Comprobantes y reposos: fotos del teléfono o PDF. */
    public const PROOF_RULE = 'mimes:jpg,jpeg,png,pdf,heic,heif,webp';

    /** Adjuntos del alumno: además documentos de oficina. */
    public const DOCUMENT_RULE = 'mimes:jpg,jpeg,png,pdf,heic,heif,webp,doc,docx,xls,xlsx';

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    public static function put(string $path, string $contents): void
    {
        Storage::disk(self::DISK)->put($path, $contents);
    }

    public static function diskFor(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function exists(?string $path): bool
    {
        return self::diskFor($path) !== null;
    }

    public static function inline(?string $path, ?string $name = null): StreamedResponse
    {
        $disk = self::diskFor($path) ?? abort(404, 'El archivo no está disponible.');

        return Storage::disk($disk)->response($path, $name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function download(?string $path, ?string $name = null): StreamedResponse
    {
        $disk = self::diskFor($path) ?? abort(404, 'El archivo no está disponible.');

        return Storage::disk($disk)->download($path, $name);
    }

    public static function delete(?string $path): void
    {
        if (! $path) {
            return;
        }
        Storage::disk(self::DISK)->delete($path);
        Storage::disk(self::LEGACY_DISK)->delete($path);
    }

    /** Admin con acceso a la sede del alumno, el propio alumno o uno de sus representantes. */
    public static function canAccessStudent(User $user, ?Student $student): bool
    {
        if (! $student) {
            return false;
        }

        return match ($user->role) {
            User::ROLE_ADMIN => CampusScope::userCanAccessCampus($user, (int) $student->campus_id),
            User::ROLE_REPRESENTATIVE => GradeAuthorization::representativeCanAccessStudent($user, $student),
            User::ROLE_STUDENT => (int) $student->user_id === (int) $user->id
                || (! $student->user_id && $user->email && strcasecmp((string) $student->email, (string) $user->email) === 0),
            default => false,
        };
    }
}
