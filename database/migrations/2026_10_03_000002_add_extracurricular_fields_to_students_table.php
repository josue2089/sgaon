<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('school_grade', 40)->nullable()->after('birth_date');
            $table->string('school_section', 40)->nullable()->after('school_grade');
            $table->string('emergency_phone', 40)->nullable()->after('mobile_phone');
            $table->string('extracurricular_level', 120)->nullable()->after('medical_notes');
            $table->text('extracurricular_objectives')->nullable()->after('extracurricular_level');
            $table->text('teacher_observations')->nullable()->after('extracurricular_objectives');
            $table->string('payment_condition', 255)->nullable()->after('teacher_observations');
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->string('nationality', 1)->nullable()->after('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'school_grade',
                'school_section',
                'emergency_phone',
                'extracurricular_level',
                'extracurricular_objectives',
                'teacher_observations',
                'payment_condition',
            ]);
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->dropColumn('nationality');
        });
    }
};
