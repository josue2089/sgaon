<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('amount');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('voided_at');
        });

        DB::table('charges')->update(['currency' => 'USD']);
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['currency', 'last_reminder_sent_at']);
        });
    }
};
