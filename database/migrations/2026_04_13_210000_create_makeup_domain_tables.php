<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('makeup_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_template_id')->nullable()->constrained('schedule_templates')->nullOnDelete();
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'session_date', 'status']);
            $table->index(['program_id', 'program_level_id']);
        });

        Schema::create('makeup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_type');
            $table->decimal('price', 10, 2);
            $table->boolean('medical_support_required')->default(false);
            $table->string('medical_support_path')->nullable();
            $table->string('status')->default('pending_payment');
            $table->text('payment_notes')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('notification_emailed_at')->nullable();
            $table->timestamp('approval_emailed_at')->nullable();
            $table->timestamp('booking_emailed_at')->nullable();
            $table->timestamps();

            $table->unique('attendance_record_id');
            $table->index(['campus_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('makeup_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('makeup_request_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });

        Schema::create('makeup_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('makeup_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('makeup_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->string('status')->default('reserved');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('makeup_request_id');
            $table->index(['makeup_session_id', 'status']);
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->foreignId('makeup_request_id')->nullable()->after('enrollment_id')->constrained('makeup_requests')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('makeup_request_id')->nullable()->after('charge_id')->constrained('makeup_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('makeup_request_id');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('makeup_request_id');
        });

        Schema::dropIfExists('makeup_bookings');
        Schema::dropIfExists('makeup_request_attachments');
        Schema::dropIfExists('makeup_requests');
        Schema::dropIfExists('makeup_sessions');
    }
};
