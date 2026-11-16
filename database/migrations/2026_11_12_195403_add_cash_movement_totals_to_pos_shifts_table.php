<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->decimal('cash_deposits', 10, 2)->default(0)->after('cash_sales');
            $table->decimal('cash_withdrawals', 10, 2)->default(0)->after('cash_refunds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropColumn(['cash_deposits', 'cash_withdrawals']);
        });
    }
};
