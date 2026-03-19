<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dateTime('paid_at_datetime')->nullable()->after('paid_at');
            $table->string('status', 30)->default('confirmed')->after('reference');
            $table->foreignId('received_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable()->after('notes');

            $table->index(['student_id', 'paid_at'], 'payments_student_paid_at_idx');
            $table->index('status', 'payments_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_student_paid_at_idx');
            $table->dropIndex('payments_status_idx');
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['paid_at_datetime', 'status', 'voided_at']);
        });
    }
};
