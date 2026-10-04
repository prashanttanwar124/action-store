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
            $table->integer('stock')->default(50)->after('unit_price');
        });

        // Initialize stock values from existing badges
        $products = DB::table('products')->get();
        foreach ($products as $p) {
            $badge = (string) ($p->stock_badge ?? '');
            if (preg_match('/(\d+)\s*left/i', $badge, $matches)) {
                $stockVal = (int) $matches[1];
            } elseif (stripos($badge, 'out of stock') !== false) {
                $stockVal = 0;
            } else {
                $stockVal = 50;
            }

            DB::table('products')->where('id', $p->id)->update(['stock' => $stockVal]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
