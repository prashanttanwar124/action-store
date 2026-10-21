<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderController;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\RewardPoint;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StripePayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Tests\TestCase;

class CustomerCommerceFlowFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
    }

    /**
     * CUST-01: In getUserOrders(), recipe kits are mapped to kit-prefixed IDs and recipe-kit types.
     */
    public function test_cust_01_reorder_maps_recipe_kit_to_kit_prefixed_id_and_type(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Basmati Rice 5kg',
            'price' => 12.99,
            'stock' => 15,
        ]);

        $kit = RecipeKit::create([
            'name' => 'Paneer Butter Masala Kit',
            'slug' => 'paneer-butter-masala-kit',
            'price' => 18.50,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-1001-A1B2',
            'status' => 'completed',
            'total' => 31.49,
            'subtotal' => 31.49,
            'delivery_fee' => 0.00,
        ]);

        // Regular product item
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 12.99,
            'quantity' => 1,
            'total_price' => 12.99,
        ]);

        // Recipe kit item (product_id is null)
        $kitOrderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'name' => $kit->name,
            'unit_price' => 18.50,
            'quantity' => 1,
            'total_price' => 18.50,
        ]);

        $response = $this->actingAs($user)->get('/reorder');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders.0.items', 2)
            ->where('dbOrders.0.items.0.id', $product->id)
            ->where('dbOrders.0.items.0.type', 'product')
            ->where('dbOrders.0.items.0.isRecipeKit', false)
            ->where('dbOrders.0.items.1.id', "kit-{$kit->id}")
            ->where('dbOrders.0.items.1.slug', 'paneer-butter-masala-kit')
            ->where('dbOrders.0.items.1.type', 'recipe-kit')
            ->where('dbOrders.0.items.1.isRecipeKit', true)
        );

        // Verify it did not fall back to the primary key of order_items table
        $this->assertNotEquals($kitOrderItem->id, "kit-{$kitOrderItem->id}");
    }

    /**
     * CUST-02: chargeMatches normalizes lines including recipe_kit_id and sorts deterministically.
     */
    public function test_cust_02_charge_matches_includes_recipe_kit_and_sorts_deterministically(): void
    {
        $user = User::factory()->create();
        $controller = app(OrderController::class);
        $method = new \ReflectionMethod($controller, 'chargeMatches');
        $method->setAccessible(true);

        $checkout = Checkout::create([
            'fingerprint' => 'test_fingerprint_cust_02',
            'expires_at' => now()->addMinutes(30),
            'user_id' => $user->id,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'subtotal' => 35.00,
            'delivery_fee' => 0.00,
            'total' => 35.00,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'stripe_payment_id' => 'pi_test_123',
            'reserved_stock' => [1 => 2, 2 => 1],
            'items' => [
                ['product_id' => null, 'recipe_kit_id' => 1, 'name' => 'Kit Alpha', 'quantity' => 1, 'unit_price' => 15.00],
                ['product_id' => null, 'recipe_kit_id' => 2, 'name' => 'Kit Beta', 'quantity' => 1, 'unit_price' => 20.00],
            ],
        ]);

        // Same items reversed in attributes: deterministic sorting must make them match
        $attributesReversed = [
            'total' => 35.00,
            'subtotal' => 35.00,
            'delivery_fee' => 0.00,
            'reserved_stock' => [1 => 2, 2 => 1],
            'items' => [
                ['product_id' => null, 'recipe_kit_id' => 2, 'name' => 'Kit Beta', 'quantity' => 1, 'unit_price' => 20.00],
                ['product_id' => null, 'recipe_kit_id' => 1, 'name' => 'Kit Alpha', 'quantity' => 1, 'unit_price' => 15.00],
            ],
        ];

        $this->assertTrue($method->invoke($controller, $checkout, $attributesReversed));

        // Swapping Kit Alpha for Kit Gamma with identical price and qty must fail (collision prevention)
        $attributesDifferentKit = [
            'total' => 35.00,
            'subtotal' => 35.00,
            'delivery_fee' => 0.00,
            'reserved_stock' => [1 => 2, 2 => 1],
            'items' => [
                ['product_id' => null, 'recipe_kit_id' => 3, 'name' => 'Kit Gamma', 'quantity' => 1, 'unit_price' => 15.00],
                ['product_id' => null, 'recipe_kit_id' => 2, 'name' => 'Kit Beta', 'quantity' => 1, 'unit_price' => 20.00],
            ],
        ];

        $this->assertFalse($method->invoke($controller, $checkout, $attributesDifferentKit));
    }

    /**
     * CUST-03: Stale expected_total returns 422 with price_changed error_code and clear message.
     */
    public function test_cust_03_checkout_stale_expected_total_returns_price_changed_error_code(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
        });

        $payload = [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 25.00, 'quantity' => 1],
            ],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
            'payment_method' => 'card',
            'expected_total' => 15.00, // Stale price: catalog has updated to $25.00
        ];

        $response = $this->actingAs($user)->postJson('/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['total']);
        $this->assertEquals('price_changed', $response->json('errors.error_code.0'));
        $this->assertStringContainsString('Order total has changed', $response->json('errors.total.0'));
    }

    /**
     * CUST-04: Home page recipe kit data contract provides bundle pricing for CTA.
     */
    public function test_cust_04_home_page_recipe_kit_cta_contract(): void
    {
        $ingredient1 = Product::factory()->create(['name' => 'Paneer', 'price' => 8.99, 'stock' => 10]);
        $ingredient2 = Product::factory()->create(['name' => 'Masala Sauce', 'price' => 7.99, 'stock' => 10]);

        $kit = RecipeKit::create([
            'name' => 'Shahi Paneer Kit',
            'slug' => 'shahi-paneer-kit',
            'price' => 13.99, // Discounted bundle price vs $16.98 loose ingredients
            'is_active' => true,
        ]);
        $kit->products()->attach($ingredient1->id, ['quantity' => 1, 'sort_order' => 1]);
        $kit->products()->attach($ingredient2->id, ['quantity' => 1, 'sort_order' => 2]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.0', fn (Assert $kitProp) => $kitProp
                ->where('id', $kit->id)
                ->where('slug', 'shahi-paneer-kit')
                ->where('price', 13.99)
                ->has('products', 2)
                ->etc()
            )
        );
    }

    /**
     * CUST-05: Custom pickup time enforces capacity limit of encompassing slot.
     */
    public function test_cust_05_custom_pickup_time_enforces_capacity_limit_of_encompassing_slot(): void
    {
        $today = now()->toDateString();
        $setting = StoreSetting::current();
        $setting->update([
            'max_orders_per_slot' => 1,
            'is_pickup_active' => true,
            'pickup_slot_start_time' => '09:00',
            'pickup_slot_end_time' => '21:00',
        ]);

        // Place an order that fills the 5:00 PM – 6:00 PM slot
        Order::factory()->create([
            'pickup_slot' => "{$today} · 5:00 PM – 6:00 PM",
            'status' => 'confirmed',
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
        });

        // Customer selects a custom time: 5:30 PM (which falls into 5:00 PM – 6:00 PM)
        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => $today,
            'pickup_time' => '17:30',
            'payment_method' => 'card',
        ];

        $response = $this->actingAs($user)->postJson('/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pickup_time']);
        $this->assertEquals('slot_full', $response->json('errors.error_code.0'));
        $this->assertStringContainsString('capacity limit', $response->json('errors.pickup_time.0'));

        // Customer selects custom time in an unbooked slot (e.g. 6:30 PM = 18:30)
        $payload['pickup_time'] = '18:30';
        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('createIntentFor')->once()->andReturn(
                PaymentIntent::constructFrom(['id' => 'pi_custom_ok', 'status' => 'requires_payment_method', 'client_secret' => 'pi_custom_ok_secret'])
            );
        });

        $okResponse = $this->actingAs($user)->postJson('/checkout', $payload);
        $okResponse->assertStatus(201);
        $this->assertDatabaseHas('checkouts', [
            'capacity_slot' => "{$today} · 6:00 PM – 7:00 PM",
        ]);

        // Verify that custom orders count towards Order::bookedInSlot
        Order::factory()->create([
            'pickup_slot' => "{$today} · 6:30 PM (Custom Time)",
            'status' => 'confirmed',
        ]);

        $this->assertEquals(1, Order::bookedInSlot("{$today} · 6:00 PM – 7:00 PM"));
    }

    /**
     * CUST-06: Inactive recipe kits return 404 on recipe-kits.show and /products/{slug} fallback.
     */
    public function test_cust_06_inactive_recipe_kit_returns_404_on_pdp_and_product_fallback(): void
    {
        $inactiveKit = RecipeKit::create([
            'name' => 'Secret Biryani Kit',
            'slug' => 'secret-biryani-kit',
            'price' => 22.00,
            'is_active' => false,
        ]);

        $this->get(route('recipe-kits.show', $inactiveKit->slug))->assertNotFound();
        $this->get('/products/'.$inactiveKit->slug)->assertNotFound();

        $activeKit = RecipeKit::create([
            'name' => 'Active Biryani Kit',
            'slug' => 'active-biryani-kit',
            'price' => 22.00,
            'is_active' => true,
        ]);

        $this->get(route('recipe-kits.show', $activeKit->slug))->assertOk();
        $this->get('/products/'.$activeKit->slug)->assertRedirect(route('recipe-kits.show', $activeKit->slug));
    }

    /**
     * CUST-07: Search recipe kits apply category and in_stock filters.
     */
    public function test_cust_07_search_recipe_kits_filter_by_category_and_in_stock(): void
    {
        $dairyProduct = Product::create([
            'name' => 'Fresh Malai Paneer',
            'slug' => 'fresh-paneer-search',
            'category' => 'dairy',
            'price' => 6.00,
            'stock' => 10,
            'stock_badge' => null,
        ]);

        $spicesProduct = Product::create([
            'name' => 'Biryani Masala',
            'slug' => 'biryani-masala-search',
            'category' => 'spices',
            'price' => 4.00,
            'stock' => 0,
            'stock_badge' => 'Out of stock',
        ]);

        $dairyKit = RecipeKit::create([
            'name' => 'Paneer Delight Kit',
            'slug' => 'paneer-delight-kit',
            'price' => 15.00,
            'is_active' => true,
        ]);
        $dairyKit->products()->attach($dairyProduct->id, ['quantity' => 1]);

        $spicesKit = RecipeKit::create([
            'name' => 'Biryani Spice Kit',
            'slug' => 'biryani-spice-kit',
            'price' => 12.00,
            'is_active' => true,
        ]);
        $spicesKit->products()->attach($spicesProduct->id, ['quantity' => 1]);

        // Category filter: category=dairy returns only paneer kit
        $resCategory = $this->get('/search?tab=kits&category=dairy');
        $resCategory->assertOk();
        $resCategory->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'paneer-delight-kit')
        );

        // In-stock filter: in_stock=1 returns only paneer kit (spices kit ingredient is stock=0)
        $resStock = $this->get('/search?tab=kits&in_stock=1');
        $resStock->assertOk();
        $resStock->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'paneer-delight-kit')
        );
    }

    /**
     * CUST-08: Reward points calculation matches net total and synchronizes on order refunds.
     */
    public function test_cust_08_reward_points_match_between_order_and_ledger_and_update_on_refund(): void
    {
        $user = User::factory()->create();
        $initialUserPoints = (int) $user->reward_points;

        // Create Home Delivery order ($28 subtotal + $4.99 delivery fee = $32.99 total)
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 28.00,
            'delivery_fee' => 4.99,
            'total' => 32.99,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => (int) floor(32.99), // 32 points
        ]);

        $this->assertSame(32, (int) $order->points_earned);
        $this->assertSame(32, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame($initialUserPoints + 32, (int) $user->fresh()->reward_points);

        // Partial refund of $10.00 (net remaining $22.99 -> 22 points)
        $order->update(['refunded_total' => 10.00]);

        $this->assertSame(22, (int) $order->fresh()->points_earned);
        $this->assertSame(22, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame($initialUserPoints + 22, (int) $user->fresh()->reward_points);

        // Order cancelled -> points reduced to 0
        $order->update(['status' => 'cancelled']);

        $this->assertSame(0, (int) $order->fresh()->points_earned);
        $this->assertSame(0, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame($initialUserPoints, (int) $user->fresh()->reward_points);
    }

    /**
     * CUST-09: Order tracking Inertia payload contains delivery_fee.
     */
    public function test_cust_09_order_tracking_inertia_payload_contains_delivery_fee(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-9999-D1F2',
            'subtotal' => 28.00,
            'delivery_fee' => 4.99,
            'total' => 32.99,
            'fulfillment_type' => 'Home Delivery',
            'status' => 'confirmed',
        ]);

        $cleanNumber = 'MM-20261021-9999-D1F2';
        $response = $this->actingAs($user)->get("/orders/{$cleanNumber}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.subtotal', fn ($v) => (float) $v === 28.0)
            ->where('order.delivery_fee', fn ($v) => (float) $v === 4.99)
            ->where('order.total', fn ($v) => (float) $v === 32.99)
        );
    }

    /**
     * CUST-10: Cart stock check endpoint throttles and enforces array bounds.
     */
    public function test_cust_10_cart_stock_check_throttling_and_array_bounds(): void
    {
        // 1. Rejects empty ids array
        $resEmpty = $this->postJson('/cart/stock-check', ['ids' => []]);
        $resEmpty->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // 2. Rejects over 50 ids
        $tooManyIds = array_map(fn ($i) => "item-{$i}", range(1, 51));
        $resOver = $this->postJson('/cart/stock-check', ['ids' => $tooManyIds]);
        $resOver->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // 3. Accepts valid IDs mix (integers, strings, kit-prefixed)
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        $kit = RecipeKit::create([
            'name' => 'Test Kit',
            'slug' => 'test-kit',
            'price' => 15.00,
            'is_active' => true,
        ]);

        $resValid = $this->postJson('/cart/stock-check', [
            'ids' => [$product->id, "kit-{$kit->id}", 'test-kit', $product->slug],
        ]);
        $resValid->assertOk();
        $resValid->assertJsonStructure(['products', 'recipeKits']);

        // 4. Rate limiting: 60 requests allowed per minute, 61st throttles
        RateLimiter::clear(sha1('cart.stock-check|127.0.0.1'));
        Cache::flush();

        for ($i = 0; $i < 60; $i++) {
            $r = $this->postJson('/cart/stock-check', ['ids' => ['test']]);
            $this->assertNotEquals(429, $r->status());
        }

        $throttled = $this->postJson('/cart/stock-check', ['ids' => ['test']]);
        $throttled->assertStatus(429);
    }

    /**
     * ADV-M3-01: Closing boundary custom pickup order is counted in bookedInSlot.
     */
    public function test_closing_boundary_custom_pickup_order_is_counted_in_booked_in_slot(): void
    {
        $date = now()->toDateString();
        Order::factory()->create([
            'pickup_slot' => "{$date} · 9:00 PM (Custom Time)",
            'status' => 'confirmed',
        ]);

        $this->assertEquals(1, Order::bookedInSlot("{$date} · 8:00 PM – 9:00 PM"));
    }

    /**
     * ADV-M3-02: Custom pickup time outside active slots throws validation exception with slot_inactive.
     */
    public function test_custom_pickup_outside_active_slots_throws_validation_exception(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);
        $date = now()->toDateString();

        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => $date,
            'pickup_time' => '03:00',
            'payment_method' => 'card',
        ];

        $response = $this->actingAs($user)->postJson('/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pickup_time']);
        $this->assertEquals('slot_inactive', $response->json('errors.error_code.0'));
        $this->assertStringContainsString('active pickup window', $response->json('errors.pickup_time.0'));
    }
}
