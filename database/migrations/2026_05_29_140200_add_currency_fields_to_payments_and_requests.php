<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('amount');
            $table->decimal('original_amount', 12, 2)->nullable()->after('currency');
            $table->decimal('exchange_rate', 16, 8)->nullable()->after('original_amount');
            $table->timestamp('exchange_rate_effective_at')->nullable()->after('exchange_rate');
            $table->foreignId('payment_method_id')->nullable()->after('exchange_rate_effective_at')->constrained()->nullOnDelete();
        });

        Schema::table('charge_payment_requests', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('amount');
            $table->decimal('original_amount', 12, 2)->nullable()->after('currency');
            $table->decimal('exchange_rate', 16, 8)->nullable()->after('original_amount');
            $table->timestamp('exchange_rate_effective_at')->nullable()->after('exchange_rate');
            $table->foreignId('payment_method_id')->nullable()->after('exchange_rate_effective_at')->constrained()->nullOnDelete();
        });

        DB::table('payments')->update([
            'currency' => 'USD',
            'original_amount' => DB::raw('amount'),
        ]);

        DB::table('charge_payment_requests')->update([
            'currency' => 'USD',
            'original_amount' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('charge_payment_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn([
                'currency',
                'original_amount',
                'exchange_rate',
                'exchange_rate_effective_at',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn([
                'currency',
                'original_amount',
                'exchange_rate',
                'exchange_rate_effective_at',
            ]);
        });
    }
};
