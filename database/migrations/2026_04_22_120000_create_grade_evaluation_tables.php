<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_evaluation_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->date('evaluated_on');
            $table->string('title')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'evaluated_on']);
        });

        Schema::create('grade_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_evaluation_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('vocabulary_rating', 32);
            $table->string('listening_rating', 32);
            $table->string('speaking_rating', 32);
            $table->string('writing_rating', 32);
            $table->string('grammar_rating', 32);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['grade_evaluation_set_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_entries');
        Schema::dropIfExists('grade_evaluation_sets');
    }
};
