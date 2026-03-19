<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('stage', 40);
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->unsignedInteger('scale_position');
            $table->unsignedInteger('scale_total')->default(12);
            $table->string('cefr_reference', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('reminder_days_before')->default(5);
            $table->timestamps();

            $table->index(['status', 'scale_position']);
        });

        DB::table('course_levels')->insert([
            ['stage' => 'Primary', 'name' => 'Primary 1', 'code' => 'P1', 'scale_position' => 1, 'scale_total' => 12, 'cefr_reference' => 'A1 Kids', 'description' => 'Inicio de la linea general de Primary.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'Primary', 'name' => 'Primary 2', 'code' => 'P2', 'scale_position' => 2, 'scale_total' => 12, 'cefr_reference' => 'A1 Kids', 'description' => 'Consolidacion de bases A1 Kids.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'Primary', 'name' => 'Primary 3', 'code' => 'P3', 'scale_position' => 3, 'scale_total' => 12, 'cefr_reference' => 'A2 Kids', 'description' => 'Transicion a desempeno A2 Kids.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'Primary', 'name' => 'Primary 4', 'code' => 'P4', 'scale_position' => 4, 'scale_total' => 12, 'cefr_reference' => 'A2 Kids', 'description' => 'Profundizacion en A2 Kids.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'Primary', 'name' => 'Primary 5', 'code' => 'P5', 'scale_position' => 5, 'scale_total' => 12, 'cefr_reference' => 'A2 General', 'description' => 'Transicion a dominio A2 General.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'Primary', 'name' => 'Primary 6', 'code' => 'P6', 'scale_position' => 6, 'scale_total' => 12, 'cefr_reference' => 'A2 General', 'description' => 'Cierre de Primary y paso a High School.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'High School', 'name' => 'High School 1', 'code' => 'HS1', 'scale_position' => 7, 'scale_total' => 12, 'cefr_reference' => 'A2', 'description' => 'Inicio de High School con consolidacion A2.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'High School', 'name' => 'High School 2', 'code' => 'HS2', 'scale_position' => 8, 'scale_total' => 12, 'cefr_reference' => 'A2', 'description' => 'Cierre de bases A2 en High School.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'High School', 'name' => 'High School 3', 'code' => 'HS3', 'scale_position' => 9, 'scale_total' => 12, 'cefr_reference' => 'B1', 'description' => 'Inicio de la franja B1.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'High School', 'name' => 'High School 4', 'code' => 'HS4', 'scale_position' => 10, 'scale_total' => 12, 'cefr_reference' => 'B1', 'description' => 'Consolidacion intermedia B1.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'High School', 'name' => 'High School 5', 'code' => 'HS5', 'scale_position' => 11, 'scale_total' => 12, 'cefr_reference' => 'B2', 'description' => 'Entrada al nivel B2.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'High School', 'name' => 'High School 6', 'code' => 'HS6', 'scale_position' => 12, 'scale_total' => 12, 'cefr_reference' => 'B2', 'description' => 'Cierre general de la escala de 12 niveles.', 'status' => 'active', 'reminder_days_before' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('course_levels');
    }
};
