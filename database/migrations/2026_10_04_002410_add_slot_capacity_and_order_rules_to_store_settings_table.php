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
        Schema::table('store_settings', function (Blueprint $table) {
            $table->unsignedInteger('max_orders_per_slot')->default(15)->nullable()->after('pickup_slot_duration_minutes');
            $table->decimal('min_order_amount', 8, 2)->default(0.00)->after('pickup_slots');
            $table->json('pickup_days')->nullable()->after('is_pickup_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn(['max_orders_per_slot', 'min_order_amount', 'pickup_days']);
        });
    }
};
