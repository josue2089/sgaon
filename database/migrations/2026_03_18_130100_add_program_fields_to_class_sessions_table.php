<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('sequence')->nullable()->after('group_id');
            $table->string('program_status', 20)->nullable()->after('topic');
            $table->text('program_notes')->nullable()->after('program_status');

            $table->index(['group_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            $table->dropColumn(['sequence', 'program_status', 'program_notes']);
        });
    }
};
