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
            $table->string('pickup_slot_window_label')->default('1-hour window')->after('pickup_time');
            $table->string('pickup_slot_start_time')->default('09:00')->after('pickup_slot_window_label');
            $table->string('pickup_slot_end_time')->default('21:00')->after('pickup_slot_start_time');
            $table->unsignedInteger('pickup_slot_duration_minutes')->default(60)->after('pickup_slot_end_time');
            $table->json('pickup_slots')->nullable()->after('pickup_slot_duration_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_slot_window_label',
                'pickup_slot_start_time',
                'pickup_slot_end_time',
                'pickup_slot_duration_minutes',
                'pickup_slots',
            ]);
        });
    }
};
