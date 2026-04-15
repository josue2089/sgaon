<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->foreignId('program_level_lesson_id')->nullable()->after('group_id')->constrained('program_level_lessons')->nullOnDelete();
            $table->unsignedInteger('planned_class_number')->nullable()->after('program_level_lesson_id');
            $table->string('planned_class_label', 60)->nullable()->after('planned_class_number');
            $table->string('planned_unit', 160)->nullable()->after('planned_class_label');
            $table->text('planned_content')->nullable()->after('planned_unit');
            $table->index(['group_id', 'planned_class_number']);
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->dropIndex(['group_id', 'planned_class_number']);
            $table->dropConstrainedForeignId('program_level_lesson_id');
            $table->dropColumn(['planned_class_number', 'planned_class_label', 'planned_unit', 'planned_content']);
        });
    }
};
