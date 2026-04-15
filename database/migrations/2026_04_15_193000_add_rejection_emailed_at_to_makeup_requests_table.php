<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('makeup_requests', function (Blueprint $table) {
            $table->timestamp('rejection_emailed_at')->nullable()->after('approval_emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('makeup_requests', function (Blueprint $table) {
            $table->dropColumn('rejection_emailed_at');
        });
    }
};
