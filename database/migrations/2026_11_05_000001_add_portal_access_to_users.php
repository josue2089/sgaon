<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #471: login con correo o cédula e invitaciones al portal. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('login_document', 40)->nullable()->unique()->after('email');
        });

        Schema::create('portal_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 20)->default('invitation');
            $table->string('token_hash', 64)->unique();
            $table->string('sent_to');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_document']);
            $table->dropColumn('login_document');
            $table->string('email')->nullable(false)->change();
        });
    }
};
