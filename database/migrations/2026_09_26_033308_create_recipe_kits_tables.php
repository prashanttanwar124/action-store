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
        Schema::create('recipe_kits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subtitle_tag')->nullable();
            $table->text('description')->nullable();
            $table->string('servings')->default('2-3 People');
            $table->string('cooking_time')->default('20 mins');
            $table->string('difficulty')->default('Easy');
            $table->decimal('price', 8, 2);
            $table->decimal('original_price', 8, 2)->nullable();
            $table->string('image')->default('/images/products/paneer_curry.jpg');
            $table->json('images')->nullable();
            $table->json('recipe_steps')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipe_kit_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_kit_id')->constrained('recipe_kits')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->string('unit_notes')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_kit_products');
        Schema::dropIfExists('recipe_kits');
    }
};
