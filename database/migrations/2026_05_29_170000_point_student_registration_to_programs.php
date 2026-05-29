<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('registration_program_id')
                ->nullable()
                ->after('campus_id')
                ->constrained('programs')
                ->nullOnDelete();
        });

        $students = DB::table('students')
            ->whereNotNull('registration_level_id')
            ->get(['id', 'registration_level_id']);

        foreach ($students as $student) {
            $academicLevel = DB::table('academic_levels')->find($student->registration_level_id);
            if (! $academicLevel) {
                continue;
            }

            $programId = $this->resolveProgramId($academicLevel->name, $academicLevel->code);
            if ($programId) {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update(['registration_program_id' => $programId]);
            }
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['registration_level_id']);
            $table->dropColumn('registration_level_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('registration_level_id')
                ->nullable()
                ->after('campus_id')
                ->constrained('academic_levels')
                ->nullOnDelete();
        });

        $students = DB::table('students')
            ->whereNotNull('registration_program_id')
            ->get(['id', 'registration_program_id', 'campus_id']);

        foreach ($students as $student) {
            $program = DB::table('programs')->find($student->registration_program_id);
            if (! $program) {
                continue;
            }

            $academicLevel = DB::table('academic_levels')
                ->where('campus_id', $student->campus_id)
                ->where(function ($query) use ($program) {
                    $query
                        ->where('name', $program->name)
                        ->orWhere('code', $program->code);
                })
                ->first();

            if ($academicLevel) {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update(['registration_level_id' => $academicLevel->id]);
            }
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['registration_program_id']);
            $table->dropColumn('registration_program_id');
        });
    }

    private function resolveProgramId(?string $name, ?string $code): ?int
    {
        if ($code) {
            $program = DB::table('programs')->where('code', $code)->first();
            if ($program) {
                return (int) $program->id;
            }
        }

        if ($name) {
            $program = DB::table('programs')->where('name', $name)->first();
            if ($program) {
                return (int) $program->id;
            }

            $normalizedName = $this->normalizeLabel($name);
            $program = DB::table('programs')
                ->get(['id', 'name'])
                ->first(fn ($row) => $this->normalizeLabel($row->name) === $normalizedName);

            if ($program) {
                return (int) $program->id;
            }
        }

        return null;
    }

    private function normalizeLabel(?string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', (string) $value));
    }
};
