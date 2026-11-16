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
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('closed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('opening_cash', 10, 2)->default(0);
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('terminal_sales', 10, 2)->default(0);
            $table->decimal('cash_refunds', 10, 2)->default(0);
            $table->decimal('terminal_refunds', 10, 2)->default(0);
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('cash_difference', 10, 2)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
