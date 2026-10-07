<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite recuperativas registradas a mano desde la ficha del alumno, sin una inasistencia asociada.
     */
    public function up(): void
    {
        Schema::table('makeup_requests', function (Blueprint $table) {
            $table->foreignId('attendance_record_id')->nullable()->change();
            $table->foreignId('class_session_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('makeup_requests', function (Blueprint $table) {
            $table->foreignId('attendance_record_id')->nullable(false)->change();
            $table->foreignId('class_session_id')->nullable(false)->change();
        });
    }
};
