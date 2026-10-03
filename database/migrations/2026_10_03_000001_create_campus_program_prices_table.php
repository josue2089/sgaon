<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus_program_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique(['campus_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_program_prices');
    }
};
