<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('hindi_title')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial core grocery categories
        $now = now();
        $categories = [
            [
                'name' => 'Staples & Flour',
                'slug' => 'staples',
                'hindi_title' => 'दाल व आटा',
                'description' => 'Fresh stone-ground flours, premium basmati rice, lentils, and everyday kitchen essentials.',
                'image' => '/images/products/atta.jpg',
                'icon' => 'Wheat',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Spices & Masalas',
                'slug' => 'spices',
                'hindi_title' => 'ताज़ा मसाले',
                'description' => 'Aromatic whole spices, pure turmeric, hand-pounded masalas, and traditional blends.',
                'image' => '/images/products/spices.jpg',
                'icon' => 'Flame',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Dairy, Paneer & Ghee',
                'slug' => 'dairy',
                'hindi_title' => 'डेयरी व देसी घी',
                'description' => 'Bilona churned pure cow ghee, fresh artisanal malai paneer, dahi, and fresh milk.',
                'image' => '/images/products/ghee.jpg',
                'icon' => 'Milk',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Snacks & Savories',
                'slug' => 'snacks',
                'hindi_title' => 'नमकीन व स्नैक्स',
                'description' => 'Crisp bhujia, mathri, roasted makhana, mukhwas, and traditional tea-time accompaniments.',
                'image' => '/images/products/snacks.jpg',
                'icon' => 'Cookie',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Indian Sweets & Mithai',
                'slug' => 'sweets',
                'hindi_title' => 'देसी मिठाई',
                'description' => 'Pure ghee kaju katli, besan laddu, gulab jamun, and festive sweet boxes made fresh.',
                'image' => '/images/products/mithai.jpg',
                'icon' => 'Sparkles',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Fresh Produce & Herbs',
                'slug' => 'produce',
                'hindi_title' => 'ताज़ी सब्ज़ियाँ',
                'description' => 'Fresh curry leaves, methi, coriander, ginger, okra, and seasonal Indian vegetables.',
                'image' => '/images/products/produce.jpg',
                'icon' => 'Apple',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pantry & Groceries',
                'slug' => 'grocery',
                'hindi_title' => 'किराना',
                'description' => 'Cold-pressed oils, pickles, papads, chutneys, and everyday Indian pantry staples.',
                'image' => '/images/products/grocery.jpg',
                'icon' => 'ShoppingBag',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Artisanal Recipe Kits',
                'slug' => 'recipe-kits',
                'hindi_title' => 'रेसिपी किट',
                'description' => 'Curated meal bundles with pre-measured spices, fresh ingredients, and chef step-by-step cards.',
                'image' => '/images/products/recipe_kits.jpg',
                'icon' => 'UtensilsCrossed',
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('categories')->insert($categories);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
