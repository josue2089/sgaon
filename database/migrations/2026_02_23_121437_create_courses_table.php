<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['campus_id', 'academic_level_id']);
            $table->unique(['campus_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
