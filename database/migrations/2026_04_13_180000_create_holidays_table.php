<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('holiday_date')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('day')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['campus_id', 'status']);
            $table->index(['is_recurring', 'month', 'day']);
            $table->index('holiday_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
