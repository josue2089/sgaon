<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('program_id')->nullable()->after('academic_level_id')->constrained()->nullOnDelete();
            $table->foreignId('program_level_id')->nullable()->after('program_id')->constrained('program_levels')->nullOnDelete();
            $table->index(['program_id', 'program_level_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['program_id', 'program_level_id']);
            $table->dropConstrainedForeignId('program_level_id');
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
