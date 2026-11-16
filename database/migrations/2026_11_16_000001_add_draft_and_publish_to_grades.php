<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #447: notas por partes y publicación a las familias.
 * - Los rubros pueden quedar vacíos (borrador).
 * - published_at: el portal solo muestra evaluaciones publicadas. Las existentes ya eran visibles, así que se marcan publicadas.
 */
return new class extends Migration
{
    private const RATINGS = ['vocabulary_rating', 'listening_rating', 'speaking_rating', 'writing_rating', 'grammar_rating'];

    public function up(): void
    {
        if (! Schema::hasColumn('grade_evaluation_sets', 'published_at')) {
            Schema::table('grade_evaluation_sets', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->after('created_by');
                $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            });

            DB::table('grade_evaluation_sets')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
        }

        Schema::table('grade_entries', function (Blueprint $table) {
            foreach (self::RATINGS as $column) {
                $table->string($column, 32)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('grade_evaluation_sets', 'published_at')) {
            Schema::table('grade_evaluation_sets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('published_by');
                $table->dropColumn('published_at');
            });
        }
        // Los rubros quedan nullable: volver a NOT NULL fallaría con borradores guardados.
    }
};
