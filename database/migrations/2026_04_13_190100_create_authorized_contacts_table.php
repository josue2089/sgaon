<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorized_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot')->default(1);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('document_id')->nullable();
            $table->string('address')->nullable();
            $table->string('home_phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('relationship')->nullable();
            $table->string('work_place')->nullable();
            $table->string('work_address')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_contacts');
    }
};
