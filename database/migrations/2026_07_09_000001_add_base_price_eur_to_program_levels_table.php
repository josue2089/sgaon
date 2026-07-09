<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_levels', function (Blueprint $table) {
            $table->decimal('base_price_eur', 10, 2)->nullable()->after('academic_hours');
        });
    }

    public function down(): void
    {
        Schema::table('program_levels', function (Blueprint $table) {
            $table->dropColumn('base_price_eur');
        });
    }
};
