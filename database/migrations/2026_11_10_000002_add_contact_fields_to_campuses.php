<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contacto de la sede para las familias: se muestra en el portal y en las páginas de error. #448 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            if (! Schema::hasColumn('campuses', 'contact_phone')) {
                $table->string('contact_phone', 40)->nullable()->after('country');
            }
            if (! Schema::hasColumn('campuses', 'contact_email')) {
                $table->string('contact_email', 160)->nullable()->after('contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            foreach (['contact_email', 'contact_phone'] as $column) {
                if (Schema::hasColumn('campuses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
