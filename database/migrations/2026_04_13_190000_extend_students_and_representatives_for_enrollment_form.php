<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('contract_number')->nullable()->after('user_id');
            $table->string('landline_phone')->nullable()->after('phone');
            $table->string('mobile_phone')->nullable()->after('landline_phone');
            $table->boolean('family_in_institution')->default(false)->after('address');
            $table->string('family_in_institution_details')->nullable()->after('family_in_institution');
            $table->foreignId('registration_level_id')->nullable()->after('campus_id')->constrained('academic_levels')->nullOnDelete();
            $table->boolean('medical_has_allergies')->default(false)->after('profile_photo_path');
            $table->text('medical_allergy_details')->nullable()->after('medical_has_allergies');
            $table->boolean('medical_has_treatment')->default(false)->after('medical_allergy_details');
            $table->text('medical_treatment_details')->nullable()->after('medical_has_treatment');
            $table->text('medical_fever_medication')->nullable()->after('medical_treatment_details');
            $table->text('medical_headache_medication')->nullable()->after('medical_fever_medication');
            $table->text('medical_notes')->nullable()->after('medical_headache_medication');
            $table->string('salesperson')->nullable()->after('medical_notes');
            $table->string('promotion')->nullable()->after('salesperson');
            $table->string('payment_method')->nullable()->after('promotion');
            $table->unsignedSmallInteger('installments')->nullable()->after('payment_method');
            $table->text('commercial_notes')->nullable()->after('installments');
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone');
            $table->string('home_phone')->nullable()->after('address');
            $table->string('mobile_phone')->nullable()->after('home_phone');
            $table->string('work_place')->nullable()->after('mobile_phone');
            $table->string('work_address')->nullable()->after('work_place');
            $table->string('office_phone')->nullable()->after('work_address');
        });
    }

    public function down(): void
    {
        Schema::table('representatives', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'home_phone',
                'mobile_phone',
                'work_place',
                'work_address',
                'office_phone',
            ]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_level_id');
            $table->dropColumn([
                'contract_number',
                'landline_phone',
                'mobile_phone',
                'family_in_institution',
                'family_in_institution_details',
                'medical_has_allergies',
                'medical_allergy_details',
                'medical_has_treatment',
                'medical_treatment_details',
                'medical_fever_medication',
                'medical_headache_medication',
                'medical_notes',
                'salesperson',
                'promotion',
                'payment_method',
                'installments',
                'commercial_notes',
            ]);
        });
    }
};
