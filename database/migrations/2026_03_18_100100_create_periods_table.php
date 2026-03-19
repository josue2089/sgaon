<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['campus_id', 'code']);
            $table->index(['campus_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
