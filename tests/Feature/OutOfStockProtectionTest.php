<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OutOfStockProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_search_include_stock_and_filter_out_of_stock_items(): void
    {
        $inStock = Product::create([
            'slug' => 'fresh-paneer',
            'name' => 'Fresh Malai Paneer',
            'category' => 'dairy',
            'price' => 5.99,
            'stock' => 15,
            'stock_badge' => 'In stock · 15 left',
        ]);

        $outOfStock = Product::create([
            'slug' => 'out-of-stock-ghee',
            'name' => 'Bilona Desi Ghee',
            'category' => 'dairy',
            'price' => 16.99,
            'stock' => 0,
            'stock_badge' => 'Out of stock',
        ]);

        $kit = RecipeKit::create([
            'slug' => 'paneer-curry-kit',
            'name' => 'Paneer Butter Masala Kit',
            'price' => 14.99,
            'is_active' => true,
        ]);
        $kit->products()->attach($outOfStock->id, ['quantity' => 1, 'sort_order' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('products', 2)
                ->where('products.1.stock', 0)
                ->where('products.1.stock_badge', 'Out of stock')
                ->where('recipeKits.0.products.0.stock', 0)
                ->where('recipeKits.0.products.0.stock_badge', 'Out of stock')
            );

        $this->get('/search?in_stock=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search')
                ->has('products.data', 1)
                ->where('products.data.0.slug', $inStock->slug)
            );
    }

    public function test_product_detail_auto_unchecks_out_of_stock_frequently_bought_together_items(): void
    {
        Product::create([
            'slug' => 'jeera-seeds',
            'name' => 'Whole Jeera',
            'category' => 'spices',
            'price' => 3.99,
            'stock' => 10,
            'stock_badge' => 'In stock · 10 left',
        ]);

        Product::create([
            'slug' => 'kashmiri-mirch',
            'name' => 'Kashmiri Lal Mirch',
            'category' => 'spices',
            'price' => 4.49,
            'stock' => 0,
            'stock_badge' => 'Out of stock',
        ]);

        $mainProduct = Product::create([
            'slug' => 'chakki-atta',
            'name' => 'Chakki Fresh Atta',
            'category' => 'atta',
            'price' => 18.99,
            'stock' => 20,
            'stock_badge' => 'In stock · 20 left',
            'frequently_bought_together' => ['jeera-seeds', 'kashmiri-mirch'],
        ]);

        $this->get("/products/{$mainProduct->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProductDetail')
                ->has('frequentlyBoughtTogether', 2)
                ->where('frequentlyBoughtTogether.0.slug', 'jeera-seeds')
                ->where('frequentlyBoughtTogether.0.checked', true)
                ->where('frequentlyBoughtTogether.0.stock', 10)
                ->where('frequentlyBoughtTogether.1.slug', 'kashmiri-mirch')
                ->where('frequentlyBoughtTogether.1.checked', false)
                ->where('frequentlyBoughtTogether.1.stock', 0)
            );
    }

    public function test_cart_stock_check_fetches_only_requested_cart_item_ids(): void
    {
        $cartProduct = Product::create([
            'slug' => 'masala-chai',
            'name' => 'Assam Masala Chai',
            'category' => 'tea',
            'price' => 6.49,
            'stock' => 0,
            'stock_badge' => 'Out of stock',
        ]);

        Product::create([
            'slug' => 'other-catalogue-item',
            'name' => 'Other Catalogue Item',
            'category' => 'spices',
            'price' => 4.99,
            'stock' => 50,
            'stock_badge' => 'In stock',
        ]);

        $this->postJson('/cart/stock-check', [
            'ids' => [$cartProduct->slug],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.slug', $cartProduct->slug)
            ->assertJsonPath('products.0.stock', 0);
    }

    public function test_order_controller_blocks_out_of_stock_products_and_kit_prefixed_recipe_kits(): void
    {
        $user = User::factory()->create();

        $oosProduct = Product::create([
            'slug' => 'oos-paneer',
            'name' => 'Out of Stock Paneer',
            'category' => 'dairy',
            'price' => 5.99,
            'stock' => 0,
            'stock_badge' => 'Out of stock',
        ]);

        $kit = RecipeKit::create([
            'slug' => 'oos-kit',
            'name' => 'Out of Stock Kit',
            'price' => 12.99,
            'is_active' => true,
        ]);
        $kit->products()->attach($oosProduct->id, ['quantity' => 1, 'sort_order' => 1]);

        $this->actingAs($user)
            ->postJson('/checkout', [
                'items' => [
                    [
                        'id' => (string) $oosProduct->id,
                        'name' => $oosProduct->name,
                        'price' => $oosProduct->price,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items'])
            ->assertJsonPath('errors.error_code.0', 'out_of_stock');

        $this->actingAs($user)
            ->postJson('/checkout', [
                'items' => [
                    [
                        'id' => "kit-{$kit->id}",
                        'name' => $kit->name,
                        'price' => $kit->price,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items'])
            ->assertJsonPath('errors.error_code.0', 'out_of_stock');
    }
}
