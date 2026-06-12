<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('access_all_campuses')->default(false)->after('is_master');
        });

        Schema::create('campus_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'campus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_all_campuses');
        });
    }
};
