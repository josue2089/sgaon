<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->foreignId('enrollment_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->after('enrollment_id')->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('period_id')->nullable()->after('group_id')->constrained('periods')->nullOnDelete();
            $table->string('charge_type', 50)->nullable()->after('concept');
            $table->string('billing_period_label', 60)->nullable()->after('charge_type');
            $table->string('origin', 40)->nullable()->after('billing_period_label');
            $table->dateTime('voided_at')->nullable()->after('notes');

            $table->index(['student_id', 'status', 'due_date'], 'charges_student_status_due_idx');
            $table->index('enrollment_id', 'charges_enrollment_idx');
            $table->index(['course_id', 'group_id', 'period_id'], 'charges_academic_context_idx');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropIndex('charges_student_status_due_idx');
            $table->dropIndex('charges_enrollment_idx');
            $table->dropIndex('charges_academic_context_idx');
            $table->dropConstrainedForeignId('period_id');
            $table->dropConstrainedForeignId('group_id');
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('enrollment_id');
            $table->dropColumn(['charge_type', 'billing_period_label', 'origin', 'voided_at']);
        });
    }
};
