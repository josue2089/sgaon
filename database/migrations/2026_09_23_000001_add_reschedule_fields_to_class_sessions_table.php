<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->boolean('date_locked')->default(false)->after('ends_at');
            $table->date('rescheduled_from')->nullable()->after('date_locked');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['date_locked', 'rescheduled_from']);
        });
    }
};
