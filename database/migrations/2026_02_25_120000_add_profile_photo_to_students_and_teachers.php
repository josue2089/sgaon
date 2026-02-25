<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('enrollment_date');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }
};
