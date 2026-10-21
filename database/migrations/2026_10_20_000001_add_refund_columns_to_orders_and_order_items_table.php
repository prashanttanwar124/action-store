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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refunded_total', 10, 2)->default(0)->after('total');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('refunded_quantity')->default(0)->after('quantity');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('total_price');
            $table->string('refund_reason')->nullable()->after('refunded_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['refunded_quantity', 'refunded_amount', 'refund_reason']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_total');
        });
    }
};
