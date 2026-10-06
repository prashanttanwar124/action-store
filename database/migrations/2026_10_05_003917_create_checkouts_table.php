<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A checkout is a payment attempt: it reserves stock and holds the cart until it is paid
     * (and turned into an order) or released. Each customer has at most one active checkout.
     */
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('stripe_payment_id')->nullable()->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method')->default('card');
            $table->string('fulfillment_type');
            $table->string('pickup_slot')->nullable();
            $table->string('pickup_location')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->json('items');
            $table->json('reserved_stock');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};
