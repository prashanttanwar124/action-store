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
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->decimal('counted_terminal', 10, 2)->nullable()->after('terminal_refunds');
            $table->decimal('terminal_difference', 10, 2)->nullable()->after('counted_terminal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropColumn(['counted_terminal', 'terminal_difference']);
        });
    }
};
