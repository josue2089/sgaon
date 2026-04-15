<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('program_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->unsignedInteger('sort_order');
            $table->unsignedInteger('program_total');
            $table->unsignedInteger('academic_hours')->default(40);
            $table->unsignedInteger('reminder_days_before')->default(5);
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'status', 'sort_order']);
        });

        Schema::create('program_level_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_level_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('class_number');
            $table->string('unit', 120)->nullable();
            $table->text('content');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['program_level_id', 'class_number']);
            $table->index(['program_level_id', 'sort_order']);
        });

        $this->seedPrograms();
    }

    public function down(): void
    {
        Schema::dropIfExists('program_level_lessons');
        Schema::dropIfExists('program_levels');
        Schema::dropIfExists('programs');
    }

    private function seedPrograms(): void
    {
        $programs = [
            [
                'name' => 'Pre-Primary',
                'code' => 'PREP',
                'description' => 'Programa inicial para etapa Pre-Primary.',
                'levels' => ['Pre-Primary 1A', 'Pre-Primary 1B', 'Pre-Primary 2A', 'Pre-Primary 2B', 'Pre-Primary 3A', 'Pre-Primary 3B', 'Pre-Primary 4A', 'Pre-Primary 4B', 'Pre-Primary 5A', 'Pre-Primary 5B', 'Pre-Primary 6A', 'Pre-Primary 6B'],
            ],
            [
                'name' => 'Primary',
                'code' => 'PRIMARY',
                'description' => 'Programa central de Primary.',
                'levels' => ['Primary 1A', 'Primary 1B', 'Primary 2A', 'Primary 2B', 'Primary 3A', 'Primary 3B', 'Primary 4A', 'Primary 4B', 'Primary 5A', 'Primary 5B', 'Primary 6A', 'Primary 6B'],
            ],
            [
                'name' => 'HighSchool',
                'code' => 'HS',
                'description' => 'Programa HighSchool editable de 14 niveles.',
                'levels' => ['HS1A', 'HS1B', 'HS2A', 'HS2B', 'HS3A', 'HS3B', 'HS4A', 'HS4B', 'Conversational 1', 'HS5A', 'HS5B', 'HS6A', 'HS6B', 'Conversational 2'],
            ],
        ];

        foreach ($programs as $programData) {
            $programId = DB::table('programs')->insertGetId([
                'name' => $programData['name'],
                'code' => $programData['code'],
                'status' => 'active',
                'description' => $programData['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $total = count($programData['levels']);
            foreach ($programData['levels'] as $index => $name) {
                $levelId = DB::table('program_levels')->insertGetId([
                    'program_id' => $programId,
                    'name' => $name,
                    'code' => strtoupper(str_replace([' ', '-'], ['', ''], $name)),
                    'sort_order' => $index + 1,
                    'program_total' => $total,
                    'academic_hours' => 40,
                    'reminder_days_before' => 5,
                    'status' => 'active',
                    'description' => "Nivel {$name} del programa {$programData['name']}.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                for ($lesson = 1; $lesson <= 20; $lesson++) {
                    DB::table('program_level_lessons')->insert([
                        'program_level_id' => $levelId,
                        'class_number' => $lesson,
                        'unit' => "Unidad {$lesson}",
                        'content' => "Contenido base de la clase {$lesson}",
                        'notes' => null,
                        'sort_order' => $lesson,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
