<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tasa cargada a mano: motivo y quién la cargó. #443 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exchange_rates', 'note')) {
            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->string('note', 255)->nullable()->after('source');
            });
        }
        if (! Schema::hasColumn('exchange_rates', 'created_by')) {
            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exchange_rates', 'created_by')) {
            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }
        if (Schema::hasColumn('exchange_rates', 'note')) {
            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
