<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('course_level_id')->nullable()->after('academic_level_id')->constrained('course_levels')->nullOnDelete();
            $table->index('course_level_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_level_id');
        });
    }
};
