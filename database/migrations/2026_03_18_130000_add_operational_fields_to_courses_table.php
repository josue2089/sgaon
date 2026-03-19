<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('teacher_id')->nullable()->after('academic_level_id')->constrained()->nullOnDelete();
            $table->foreignId('period_id')->nullable()->after('teacher_id')->constrained('periods')->nullOnDelete();
            $table->foreignId('schedule_template_id')->nullable()->after('period_id')->constrained('schedule_templates')->nullOnDelete();
            $table->date('start_date')->nullable()->after('description');
            $table->date('end_date')->nullable()->after('start_date');
            $table->unsignedInteger('academic_hours')->nullable()->after('end_date');
            $table->foreignId('managed_group_id')->nullable()->after('academic_hours')->constrained('groups')->nullOnDelete();

            $table->index(['teacher_id', 'period_id', 'schedule_template_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('managed_group_id');
            $table->dropColumn('academic_hours');
            $table->dropColumn('end_date');
            $table->dropColumn('start_date');
            $table->dropConstrainedForeignId('schedule_template_id');
            $table->dropConstrainedForeignId('period_id');
            $table->dropConstrainedForeignId('teacher_id');
        });
    }
};
