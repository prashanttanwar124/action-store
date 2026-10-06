<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why a paid checkout could not be fulfilled. Once set, the checkout is only ever refunded,
     * never turned into an order, even if stock or slot capacity comes back.
     */
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->string('fulfillment_failure')->nullable()->after('reserved_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn('fulfillment_failure');
        });
    }
};
