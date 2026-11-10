<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plan de clases oficial (plantillas File A/B) y estructura de clase por programa. #470 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_level_lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('program_level_lessons', 'lesson_type')) {
                $table->string('lesson_type', 20)->nullable()->after('content');
            }
            if (! Schema::hasColumn('program_level_lessons', 'activity_tags')) {
                $table->json('activity_tags')->nullable()->after('lesson_type');
            }
            if (! Schema::hasColumn('program_level_lessons', 'from_template')) {
                $table->boolean('from_template')->default(false)->after('activity_tags');
            }
        });

        Schema::table('programs', function (Blueprint $table) {
            if (! Schema::hasColumn('programs', 'class_framework')) {
                $table->json('class_framework')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('program_level_lessons', function (Blueprint $table) {
            foreach (['from_template', 'activity_tags', 'lesson_type'] as $column) {
                if (Schema::hasColumn('program_level_lessons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'class_framework')) {
                $table->dropColumn('class_framework');
            }
        });
    }
};
