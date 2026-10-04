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
            $table->unsignedSmallInteger('prep_time_minutes')->default(15)->after('opening_hours');
            $table->unsignedSmallInteger('busy_mode_extra_minutes')->default(0)->after('prep_time_minutes');
            $table->string('busy_mode_reason')->nullable()->after('busy_mode_extra_minutes');
            $table->boolean('is_delivery_active')->default(false)->after('is_pickup_active');
            $table->json('delivery_days')->nullable()->after('is_delivery_active');
            $table->decimal('delivery_fee', 8, 2)->default(4.99)->after('delivery_days');
            $table->decimal('free_delivery_threshold', 8, 2)->default(50.00)->after('delivery_fee');
            $table->string('delivery_estimated_time')->default('Same Day 5:00 PM – 8:00 PM')->after('free_delivery_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'prep_time_minutes',
                'busy_mode_extra_minutes',
                'busy_mode_reason',
                'is_delivery_active',
                'delivery_days',
                'delivery_fee',
                'free_delivery_threshold',
                'delivery_estimated_time',
            ]);
        });
    }
};
