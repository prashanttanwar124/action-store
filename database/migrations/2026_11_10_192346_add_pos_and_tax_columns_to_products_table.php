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
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->unique()->after('slug');
            $table->string('sku', 64)->nullable()->unique()->after('barcode');
            $table->string('unit_type', 20)->default('each')->after('unit_price');
            $table->boolean('is_taxable')->default(false)->after('unit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropUnique(['sku']);
            $table->dropColumn(['barcode', 'sku', 'unit_type', 'is_taxable']);
        });
    }
};
