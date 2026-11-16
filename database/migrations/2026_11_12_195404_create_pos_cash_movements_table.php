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
        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_shift_id')->constrained('pos_shifts')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 10, 2);
            $table->string('reason', 120);
            $table->string('notes', 500)->nullable();
            $table->decimal('balance_after', 10, 2);
            $table->timestamps();

            $table->index(['pos_shift_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
