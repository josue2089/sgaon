<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->text('topic')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'session_date', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
