<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #442: marca las inscripciones hechas por la familia desde el portal, para avisarle al admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->timestamp('renewed_from_portal_at')->nullable()->after('enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('renewed_from_portal_at');
        });
    }
};
