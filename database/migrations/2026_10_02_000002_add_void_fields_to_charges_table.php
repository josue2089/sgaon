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
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable()->after('voided_by');
        });

        // Motivo de las anulaciones hechas por finance:void-legacy-recurring-charges (#142), que solo quedó en notes.
        DB::table('charges')
            ->whereNotNull('voided_at')
            ->whereNull('void_reason')
            ->where('notes', 'like', '%Anulado: Cargo legacy 75$ no corresponde%')
            ->update(['void_reason' => 'Cargo legacy 75$ no corresponde']);

        DB::table('payments')
            ->whereNotNull('voided_at')
            ->whereNull('void_reason')
            ->where('notes', 'like', '%Anulado: Pago "NO APLICA"%')
            ->update(['void_reason' => 'Pago "NO APLICA" registrado para saldar cargo legacy 75$']);
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('void_reason');
        });
    }
};
