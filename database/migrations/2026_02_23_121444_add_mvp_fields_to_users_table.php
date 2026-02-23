<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role', 30)->default('admin')->after('password');
            $table->string('phone')->nullable()->after('email');
            $table->string('status', 20)->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campus_id');
            $table->dropColumn(['role', 'phone', 'status']);
        });
    }
};
