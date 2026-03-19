<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->json('days');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['campus_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
