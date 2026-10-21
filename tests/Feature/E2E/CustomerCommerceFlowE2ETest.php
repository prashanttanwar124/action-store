<?php

namespace Tests\Feature\E2E;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerCommerceFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Travel to store business hours (2:00 PM)
        $this->travelTo(now()->setTime(14, 0));

        $this->user = User::factory()->create([
            'name' => 'Aditi Rao',
            'email' => 'aditi.rao@example.com',
        ]);
    }

    public function test_storefront_home_page_renders_with_sliders_kits_and_products(): void
    {
        // Setup active and inactive sliders
        Slider::create([
            'title' => 'Diwali Festive Sale',
            'subtitle' => 'Save up to 30% on Sweets & Snacks',
            'image' => '/images/sliders/festive.jpg',
            'link_url' => '/search?category=sweets',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Slider::create([
            'title' => 'Expired Summer Promo',
            'image' => '/images/sliders/summer.jpg',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        // Setup active and inactive recipe kits
        $activeKit = RecipeKit::create([
            'name' => 'Shahi Paneer Kit',
            'slug' => 'shahi-paneer-kit',
            'price' => 16.99,
            'is_active' => true,
        ]);
        RecipeKit::create([
            'name' => 'Draft Biryani Kit',
            'slug' => 'draft-biryani-kit',
            'price' => 24.99,
            'is_active' => false,
        ]);

        // Setup products
        Product::factory()->create([
            'name' => 'Organic Ghee',
            'slug' => 'organic-ghee',
            'price' => 14.50,
            'category' => 'dairy',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('sliders', 1)
            ->where('sliders.0.title', 'Diwali Festive Sale')
            ->has('recipeKits', 1)
            ->where('recipeKits.0.slug', 'shahi-paneer-kit')
            ->where('totalRecipeKitCount', 1)
            ->has('products', 1)
            ->where('totalProductCount', 1)
        );
    }

    public function test_storefront_product_search_and_category_filtering(): void
    {
        $category = Category::where('slug', 'spices')->first() ?? Category::create([
            'name' => 'Spices & Seasonings',
            'slug' => 'spices',
            'is_active' => true,
        ]);

        $haldi = Product::factory()->create([
            'name' => 'Everest Haldi Powder',
            'slug' => 'everest-haldi-powder',
            'price' => 3.99,
            'stock' => 25,
            'category' => 'spices',
            'category_title' => 'Spices & Seasonings',
        ]);

        $mirchi = Product::factory()->create([
            'name' => 'Deggi Mirch Powder',
            'slug' => 'deggi-mirch-powder',
            'price' => 4.49,
            'stock' => 0,
            'category' => 'spices',
            'category_title' => 'Spices & Seasonings',
        ]);

        $paneer = Product::factory()->create([
            'name' => 'Nanak Paneer Block',
            'slug' => 'nanak-paneer-block',
            'price' => 7.99,
            'stock' => 15,
            'category' => 'dairy',
            'category_title' => 'Dairy',
        ]);

        // Keyword search for "Haldi"
        $searchResponse = $this->get('/search?q=Haldi');
        $searchResponse->assertOk();
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'everest-haldi-powder')
        );

        // Category filter route
        $catResponse = $this->get('/categories/spices');
        $catResponse->assertOk();
        $catResponse->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('products.data', 2)
            ->where('filters.category', 'spices')
        );

        // In-stock filter
        $inStockResponse = $this->get('/search?category=spices&in_stock=1');
        $inStockResponse->assertOk();
        $inStockResponse->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'everest-haldi-powder')
        );
    }

    public function test_product_detail_page_loads_with_frequently_bought_together(): void
    {
        $primary = Product::factory()->create([
            'name' => 'Sona Masoori Rice 10lb',
            'slug' => 'sona-masoori-rice',
            'price' => 19.99,
            'category' => 'staples',
        ]);

        $companion1 = Product::factory()->create([
            'name' => 'Toor Dal 2lb',
            'slug' => 'toor-dal',
            'price' => 5.49,
            'category' => 'staples',
        ]);

        $companion2 = Product::factory()->create([
            'name' => 'Amul Pure Ghee 1L',
            'slug' => 'amul-pure-ghee',
            'price' => 15.99,
            'category' => 'dairy',
        ]);

        $primary->update([
            'frequently_bought_together' => [
                ['slug' => $companion1->slug],
                ['slug' => $companion2->slug],
            ],
        ]);

        $response = $this->get('/products/'.$primary->slug);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProductDetail')
            ->where('slug', 'sona-masoori-rice')
            ->where('product.name', 'Sona Masoori Rice 10lb')
            ->has('frequentlyBoughtTogether')
        );
    }

    public function test_product_detail_page_redirects_to_recipe_kit_when_slug_matches_kit(): void
    {
        $kit = RecipeKit::create([
            'name' => 'Chole Bhature Kit',
            'slug' => 'chole-bhature-kit',
            'price' => 13.99,
            'is_active' => true,
        ]);

        $response = $this->get('/products/'.$kit->slug);

        $response->assertRedirect(route('recipe-kits.show', $kit->slug));
    }

    public function test_recipe_kit_detail_page_loads_with_ingredients_and_pricing(): void
    {
        $flour = Product::factory()->create(['name' => 'Maida Flour', 'price' => 3.99, 'stock' => 20]);
        $chana = Product::factory()->create(['name' => 'Kabuli Chana', 'price' => 4.49, 'stock' => 15]);

        $kit = RecipeKit::create([
            'name' => 'Delhi Chole Bhature Kit',
            'slug' => 'delhi-chole-bhature-kit',
            'subtitle_tag' => 'STREET FOOD SPECIAL',
            'price' => 11.99,
            'original_price' => 14.99,
            'servings' => 'Serves 4',
            'cooking_time' => '35 mins',
            'difficulty' => 'Medium',
            'is_active' => true,
        ]);

        $kit->products()->attach([
            $flour->id => ['quantity' => 1, 'unit_notes' => '1 kg package', 'sort_order' => 1],
            $chana->id => ['quantity' => 2, 'unit_notes' => '500 g pouch', 'sort_order' => 2],
        ]);

        $response = $this->get('/recipe-kits/'.$kit->slug);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('RecipeKitDetail')
            ->where('kit.slug', 'delhi-chole-bhature-kit')
            ->where('kit.price', 11.99)
            ->where('kit.servings', 'Serves 4')
            ->has('kit.products', 2)
        );
    }

    public function test_cart_stock_check_returns_current_stock_and_prices(): void
    {
        $product = Product::factory()->create(['slug' => 'maggi-noodles', 'price' => 2.99, 'stock' => 40]);
        $kit = RecipeKit::create(['name' => 'Biryani Kit', 'slug' => 'biryani-kit', 'price' => 21.00, 'is_active' => true]);

        $response = $this->postJson('/cart/stock-check', [
            'ids' => [
                (string) $product->id,
                'kit-'.$kit->slug,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonPath('products.0.stock', 40)
            ->assertJsonPath('recipeKits.0.slug', 'biryani-kit');
    }

    public function test_customer_can_complete_store_pickup_order_and_earn_reward_points(): void
    {
        $product1 = Product::factory()->create(['name' => 'Kesar Mango Pulp', 'price' => 5.50, 'stock' => 10]);
        $product2 = Product::factory()->create(['name' => 'Cardamom Pods', 'price' => 6.50, 'stock' => 10]);

        $payload = [
            'items' => [
                ['id' => $product1->id, 'name' => $product1->name, 'price' => 5.50, 'quantity' => 2],
                ['id' => $product2->id, 'name' => $product2->name, 'price' => 6.50, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
            'customer_phone' => '4165550199',
            'notes' => 'Please pack in eco-friendly paper bags',
        ];

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $orderId = $response->json('order.id');
        $this->assertNotNull($orderId);

        // Subtotal = 2 * 5.50 + 6.50 = 17.50
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $this->user->id,
            'subtotal' => 17.50,
            'delivery_fee' => 0.00,
            'total' => 17.50,
            'status' => 'confirmed',
            'points_earned' => 17,
        ]);

        // Inventory is decremented
        $this->assertEquals(8, $product1->fresh()->stock);
        $this->assertEquals(9, $product2->fresh()->stock);

        // Customer reward points: 100 welcome + 17 earned = 117
        $this->assertEquals(117, $this->user->fresh()->reward_points);
    }

    public function test_customer_can_complete_home_delivery_order(): void
    {
        StoreSetting::current()->update([
            'is_delivery_active' => true,
            'delivery_days' => [strtolower(now()->format('l'))],
        ]);

        $product = Product::factory()->create(['name' => 'Basmati Rice 20lb', 'price' => 28.00, 'stock' => 5]);

        $payload = [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 28.00, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '789 Bay Street, Suite 1204, Toronto ON',
            'customer_phone' => '4165559876',
        ];

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $orderNumber = $response->json('order.order_number');

        $this->assertDatabaseHas('orders', [
            'order_number' => $orderNumber,
            'subtotal' => 28.00,
            'delivery_fee' => 4.99,
            'total' => 32.99,
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '789 Bay Street, Suite 1204, Toronto ON',
        ]);
    }

    public function test_customer_can_view_live_order_tracking(): void
    {
        $product = Product::factory()->create(['name' => 'Masala Chai Blend', 'price' => 8.99]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'customer_name' => $this->user->name,
            'customer_email' => $this->user->email,
            'subtotal' => 8.99,
            'total' => 8.99,
            'status' => 'confirmed',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 8.99,
            'quantity' => 1,
            'total_price' => 8.99,
        ]);

        $cleanNumber = ltrim($order->order_number, '#');
        $response = $this->actingAs($this->user)->get("/orders/{$cleanNumber}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.order_number', $order->order_number)
            ->where('order.status', 'confirmed')
            ->where('order.total', 8.99)
            ->has('order.items', 1)
            ->where('order.items.0.name', 'Masala Chai Blend')
        );
    }

    public function test_customer_account_dashboard_shows_order_history_and_reorder_details(): void
    {
        $product = Product::factory()->create(['name' => 'Alphonso Mango Pulp', 'price' => 6.00]);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total' => 12.50,
            'subtotal' => 12.50,
            'status' => 'completed',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 6.25,
            'quantity' => 2,
            'total_price' => 12.50,
        ]);

        // Visit main account page
        $response = $this->actingAs($this->user->refresh())->get('/account');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders', 1)
            ->where('dbOrders.0.id', $order->order_number)
            ->where('dbOrders.0.total', 12.50)
            ->where('dbOrders.0.itemCount', 2)
            ->where('auth.user.reward_points', 112)
        );

        // Visit reorder section
        $reorderResponse = $this->actingAs($this->user)->get('/reorder');
        $reorderResponse->assertOk();
        $reorderResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
            ->has('dbOrders', 1)
        );
    }
}
