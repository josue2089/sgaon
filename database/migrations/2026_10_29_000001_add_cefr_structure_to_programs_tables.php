<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #469: equivalencias CEFR/GSE, público y edades por programa, y siguiente nivel entre programas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('audience', 20)->nullable()->after('is_extracurricular');
            $table->unsignedTinyInteger('age_min')->nullable()->after('audience');
            $table->unsignedTinyInteger('age_max')->nullable()->after('age_min');
            $table->boolean('uses_cefr')->default(false)->after('age_max');
        });

        Schema::table('program_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('level_number')->nullable()->after('sort_order');
            $table->string('part', 1)->nullable()->after('level_number');
            $table->string('cefr_label', 40)->nullable()->after('part');
            $table->unsignedTinyInteger('gse_min')->nullable()->after('cefr_label');
            $table->unsignedTinyInteger('gse_max')->nullable()->after('gse_min');
            $table->string('book', 120)->nullable()->after('gse_max');
            $table->foreignId('next_program_level_id')->nullable()->after('book')
                ->constrained('program_levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('program_levels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_program_level_id');
            $table->dropColumn(['level_number', 'part', 'cefr_label', 'gse_min', 'gse_max', 'book']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['audience', 'age_min', 'age_max', 'uses_cefr']);
        });
    }
};
