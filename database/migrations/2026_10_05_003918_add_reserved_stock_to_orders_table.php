<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the exact product quantities taken from stock (including recipe-kit ingredients),
     * so cancelling an order returns precisely what it reserved.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('reserved_stock')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reserved_stock');
        });
    }
};
