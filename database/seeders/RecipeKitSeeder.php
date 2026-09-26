<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\RecipeKit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecipeKitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('recipe_kit_products')->truncate();
        RecipeKit::truncate();
        Schema::enableForeignKeyConstraints();

        $kits = [
            [
                'slug' => 'paneer-curry',
                'name' => 'Paneer Butter Masala Kit',
                'subtitle_tag' => 'CHEF-CRAFTED · DINNER IN 20 MINS',
                'description' => 'Everything you need to make rich, silky restaurant-style Paneer Butter Masala in 20 minutes. All pre-portioned fresh ingredients & whole spices bundled together with group savings.',
                'servings' => 'Serves 4',
                'cooking_time' => '20 mins',
                'difficulty' => 'Easy',
                'price' => 14.99,
                'original_price' => 18.99,
                'image' => '/images/products/paneer_curry.jpg',
                'images' => [
                    '/images/products/paneer_curry.jpg',
                    '/images/products/ghee.jpg',
                    '/images/products/garam_masala.jpg',
                ],
                'recipe_steps' => [
                    'Heat 2 tbsp Desi Ghee in a heavy pan, crackle whole garam masala spices.',
                    'Add puree and simmer on low-medium flame until aromatic.',
                    'Fold in fresh cubed paneer gently and sprinkle crushed kasuri methi.',
                    'Simmer for 3 minutes and finish with fresh cream swirl.',
                ],
                'is_active' => true,
                'products_slugs' => [
                    ['slug' => 'paneer', 'quantity' => 1, 'unit_notes' => '400g cubed malai paneer', 'is_optional' => false],
                    ['slug' => 'ghee', 'quantity' => 1, 'unit_notes' => 'Pure cow butter for gravy base', 'is_optional' => false],
                    ['slug' => 'garam-masala', 'quantity' => 1, 'unit_notes' => 'Makhani gravy spice blend', 'is_optional' => false],
                ],
            ],
            [
                'slug' => 'biryani',
                'name' => 'Royal Dum Biryani Kit',
                'subtitle_tag' => 'HYDERABADI DUM SPECIAL',
                'description' => 'Layered aromatic Hyderabadi Dum Biryani kit. Contains 2-year aged long-grain basmati, authentic biryani pot masala, pure saffron strands, fried crispy onions, and desi ghee.',
                'servings' => 'Serves 4',
                'cooking_time' => '45 mins',
                'difficulty' => 'Medium',
                'price' => 19.99,
                'original_price' => 24.49,
                'image' => '/images/products/biryani.jpg',
                'images' => [
                    '/images/products/biryani.jpg',
                    '/images/products/rice.jpg',
                    '/images/products/ghee.jpg',
                ],
                'recipe_steps' => [
                    'Parboil aged Basmati Rice with whole bay leaf and cloves to 70% doneness.',
                    'Layer spiced vegetables or paneer marinated in yogurt and biryani pot spice blend.',
                    'Top with golden barista fried onions, pure saffron milk, and hot desi ghee drizzle.',
                    'Seal lid tightly with dough and dum-cook on low flame for 20 minutes.',
                ],
                'is_active' => true,
                'products_slugs' => [
                    ['slug' => 'rice', 'quantity' => 1, 'unit_notes' => '2-year aged royal long-grain basmati', 'is_optional' => false],
                    ['slug' => 'ghee', 'quantity' => 1, 'unit_notes' => 'For dum layering and sizzle', 'is_optional' => false],
                    ['slug' => 'garam-masala', 'quantity' => 1, 'unit_notes' => 'Shahi 12-spice biryani pot masala', 'is_optional' => false],
                ],
            ],
            [
                'slug' => 'chana-masala',
                'name' => 'Punjabi Chana Masala Kit',
                'subtitle_tag' => 'AMRITSARI PIND RECIPE',
                'description' => 'Authentic Amritsari Chana Masala kit with roasted anardana (pomegranate seed), amchur, freshly ground garam masala, and ginger juliennes.',
                'servings' => 'Serves 4',
                'cooking_time' => '30 mins',
                'difficulty' => 'Easy',
                'price' => 11.99,
                'original_price' => 14.49,
                'image' => '/images/products/garam_masala.jpg',
                'images' => [
                    '/images/products/garam_masala.jpg',
                    '/images/products/ghee.jpg',
                ],
                'recipe_steps' => [
                    'Boil chickpeas with black tea bag for rich dark restaurant color.',
                    'Temper ginger juliennes, green chillies and roasted anardana spice mix in ghee.',
                    'Simmer until chickpeas are tender and gravy is deeply spiced.',
                ],
                'is_active' => true,
                'products_slugs' => [
                    ['slug' => 'garam-masala', 'quantity' => 1, 'unit_notes' => 'Amritsari chana masala spice pack', 'is_optional' => false],
                    ['slug' => 'ghee', 'quantity' => 1, 'unit_notes' => 'For tempering & sizzle', 'is_optional' => false],
                ],
            ],
            [
                'slug' => 'dal-tadka',
                'name' => 'Dal Tadka & Jeera Rice Kit',
                'subtitle_tag' => 'COMFORT FOOD ESSENTIAL',
                'description' => 'Smoky dhaba-style yellow dal tadka paired with fragrant cumin jeera rice. Comes with pure desi ghee for the sizzle tempering and whole unpolished toor dal.',
                'servings' => 'Serves 4',
                'cooking_time' => '20 mins',
                'difficulty' => 'Easy',
                'price' => 10.99,
                'original_price' => 13.99,
                'image' => '/images/products/toor_dal.jpg',
                'images' => [
                    '/images/products/toor_dal.jpg',
                    '/images/products/rice.jpg',
                    '/images/products/ghee.jpg',
                ],
                'recipe_steps' => [
                    'Pressure cook organic toor dal with turmeric and salt until creamy soft.',
                    'Cook fragrant basmati rice with cumin seeds and whole cloves.',
                    'Heat desi ghee until smoking, crackle cumin seeds, garlic, and dried red chillies.',
                    'Pour sizzle tadka over hot dal and serve immediately with jeera rice.',
                ],
                'is_active' => true,
                'products_slugs' => [
                    ['slug' => 'toor-dal', 'quantity' => 1, 'unit_notes' => 'Organic unpolished yellow pigeon peas', 'is_optional' => false],
                    ['slug' => 'rice', 'quantity' => 1, 'unit_notes' => 'For fragrant jeera rice', 'is_optional' => false],
                    ['slug' => 'ghee', 'quantity' => 1, 'unit_notes' => 'For the garlic cumin sizzle tadka', 'is_optional' => false],
                ],
            ],
        ];

        foreach ($kits as $kitData) {
            $slugs = $kitData['products_slugs'] ?? [];
            unset($kitData['products_slugs']);

            $kit = RecipeKit::create($kitData);

            $syncData = [];
            foreach ($slugs as $idx => $item) {
                $product = Product::where('slug', $item['slug'])->first();
                if ($product) {
                    $syncData[$product->id] = [
                        'quantity' => $item['quantity'],
                        'unit_notes' => $item['unit_notes'],
                        'is_optional' => $item['is_optional'],
                        'sort_order' => $idx,
                    ];
                }
            }

            if (! empty($syncData)) {
                $kit->products()->sync($syncData);
            }
        }
    }
}
