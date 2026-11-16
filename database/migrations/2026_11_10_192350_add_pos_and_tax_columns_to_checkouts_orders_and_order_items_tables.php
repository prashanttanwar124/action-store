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
        Schema::table('checkouts', function (Blueprint $table) {
            $table->decimal('tax', 10, 2)->default(0)->after('subtotal');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->change();
            $table->string('channel', 20)->default('online')->index()->after('order_number');
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('admins')->nullOnDelete();
            $table->foreignId('pos_shift_id')->nullable()->after('admin_id')->constrained('pos_shifts')->nullOnDelete();
            $table->decimal('tax', 10, 2)->default(0)->after('discount');
            $table->unsignedInteger('points_redeemed')->default(0)->after('points_earned');
            $table->decimal('points_discount', 10, 2)->default(0)->after('points_redeemed');
            $table->decimal('cash_amount', 10, 2)->default(0)->after('payment_method');
            $table->decimal('terminal_amount', 10, 2)->default(0)->after('cash_amount');
            $table->decimal('cash_tendered', 10, 2)->nullable()->after('terminal_amount');
            $table->decimal('change_due', 10, 2)->nullable()->after('cash_tendered');
            $table->string('payment_reference', 120)->nullable()->after('stripe_payment_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('unit_type', 20)->default('each')->after('size');
            $table->decimal('weight', 8, 3)->nullable()->after('quantity');
            $table->boolean('is_taxable')->default(false)->after('total_price');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('is_taxable');
            $table->unsignedInteger('restocked_quantity')->default(0)->after('refunded_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_type', 'weight', 'is_taxable', 'tax_amount', 'restocked_quantity']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_shift_id');
            $table->dropConstrainedForeignId('admin_id');
            $table->dropIndex(['channel']);
            $table->dropColumn([
                'channel',
                'tax',
                'points_redeemed',
                'points_discount',
                'cash_amount',
                'terminal_amount',
                'cash_tendered',
                'change_due',
                'payment_reference',
            ]);
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn('tax');
        });
    }
};
