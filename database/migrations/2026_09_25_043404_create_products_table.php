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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('subtitle_tag')->nullable();
            $table->string('category')->default('grocery');
            $table->string('category_title')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('original_price', 8, 2)->nullable();
            $table->string('unit_price')->nullable();
            $table->string('stock_badge')->nullable();
            $table->string('photo_label')->nullable();
            $table->string('image')->default('/images/products/atta.jpg');
            $table->string('size_main')->nullable();
            $table->string('size_sub')->nullable();
            $table->string('freshness_line')->nullable();
            $table->text('description')->nullable();
            $table->string('servings')->nullable();
            $table->string('cooking_time')->nullable();
            $table->boolean('is_recipe_kit')->default(false);
            $table->boolean('buy_again')->default(false);
            $table->boolean('has_subscription')->default(false);
            $table->json('frequently_bought_together')->nullable();
            $table->json('recipe_ingredients')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
