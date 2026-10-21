<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\RewardPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChallengerPricingSearchInactivePdpTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Objective 1: Inactive Recipe Kit PDP Access Tests
    |--------------------------------------------------------------------------
    */

    public function test_inactive_recipe_kit_returns_404_on_recipe_kit_pdp(): void
    {
        $kit = RecipeKit::create([
            'name' => 'Hidden Truffle Pasta Kit',
            'slug' => 'hidden-truffle-pasta-kit',
            'price' => 34.50,
            'is_active' => false,
        ]);

        $response = $this->get('/recipe-kits/'.$kit->slug);
        $response->assertNotFound();
    }

    public function test_inactive_recipe_kit_returns_404_on_product_fallback_lookup(): void
    {
        $kit = RecipeKit::create([
            'name' => 'Hidden Kashmiri Rogan Kit',
            'slug' => 'hidden-kashmiri-rogan-kit',
            'price' => 28.00,
            'is_active' => false,
        ]);

        // Attempting to access via /products/{slug} must not redirect to inactive kit
        $response = $this->get('/products/'.$kit->slug);
        $response->assertNotFound();
    }

    public function test_active_recipe_kit_redirects_from_product_pdp_to_recipe_kit_pdp(): void
    {
        $kit = RecipeKit::create([
            'name' => 'Active Kashmiri Rogan Kit',
            'slug' => 'active-kashmiri-rogan-kit',
            'price' => 28.00,
            'is_active' => true,
        ]);

        $response = $this->get('/products/'.$kit->slug);
        $response->assertRedirect('/recipe-kits/'.$kit->slug);

        $pdpResponse = $this->get('/recipe-kits/'.$kit->slug);
        $pdpResponse->assertOk();
        $pdpResponse->assertInertia(fn (Assert $page) => $page
            ->component('RecipeKitDetail')
            ->where('kit.slug', $kit->slug)
            ->where('kit.is_active', true)
        );
    }

    public function test_recipe_kits_index_excludes_inactive_kits(): void
    {
        $activeKit = RecipeKit::create([
            'name' => 'Visible Kit',
            'slug' => 'visible-kit',
            'price' => 19.99,
            'is_active' => true,
        ]);

        $inactiveKit = RecipeKit::create([
            'name' => 'Secret Inactive Kit',
            'slug' => 'secret-inactive-kit',
            'price' => 19.99,
            'is_active' => false,
        ]);

        $response = $this->get('/recipe-kits');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'visible-kit')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Objective 2: Recipe Kit Search Filtering Tests
    |--------------------------------------------------------------------------
    */

    public function test_search_filters_recipe_kits_by_ingredient_category(): void
    {
        $dairyProduct = Product::create([
            'name' => 'Organic Paneer Slab',
            'slug' => 'organic-paneer-slab',
            'category' => 'dairy',
            'price' => 7.50,
            'stock' => 20,
        ]);

        $spiceProduct = Product::create([
            'name' => 'Garam Masala Blend',
            'slug' => 'garam-masala-blend',
            'category' => 'spices',
            'price' => 4.50,
            'stock' => 15,
        ]);

        $produceProduct = Product::create([
            'name' => 'Fresh Spinach Bunch',
            'slug' => 'fresh-spinach-bunch',
            'category' => 'produce',
            'price' => 2.50,
            'stock' => 30,
        ]);

        // Kit 1: Contains Dairy only
        $paneerKit = RecipeKit::create([
            'name' => 'Classic Paneer Kit',
            'slug' => 'classic-paneer-kit',
            'price' => 12.00,
            'is_active' => true,
        ]);
        $paneerKit->products()->attach($dairyProduct->id, ['quantity' => 1]);

        // Kit 2: Contains Spices and Produce
        $saagKit = RecipeKit::create([
            'name' => 'Desi Saag Kit',
            'slug' => 'desi-saag-kit',
            'price' => 10.00,
            'is_active' => true,
        ]);
        $saagKit->products()->attach($spiceProduct->id, ['quantity' => 1]);
        $saagKit->products()->attach($produceProduct->id, ['quantity' => 2]);

        // 1. Filter by category=dairy: should only return paneerKit
        $dairyResponse = $this->get('/search?tab=kits&category=dairy');
        $dairyResponse->assertOk();
        $dairyResponse->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'classic-paneer-kit')
        );

        // 2. Filter by category=produce: should only return saagKit
        $produceResponse = $this->get('/search?tab=kits&category=produce');
        $produceResponse->assertOk();
        $produceResponse->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'desi-saag-kit')
        );

        // 3. Filter by category=spices: should only return saagKit
        $spicesResponse = $this->get('/search?tab=kits&category=spices');
        $spicesResponse->assertOk();
        $spicesResponse->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'desi-saag-kit')
        );

        // 4. Filter by category=bakery (neither kit has this): should return 0 kits
        $bakeryResponse = $this->get('/search?tab=kits&category=bakery');
        $bakeryResponse->assertOk();
        $bakeryResponse->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 0)
        );
    }

    public function test_search_filters_recipe_kits_by_in_stock_excluding_kits_with_unavailable_ingredients(): void
    {
        $inStockProduct1 = Product::create([
            'name' => 'Basmati Rice Premium',
            'slug' => 'basmati-rice-premium',
            'category' => 'staples',
            'price' => 12.00,
            'stock' => 50,
            'stock_badge' => null,
        ]);

        $inStockProduct2 = Product::create([
            'name' => 'Saffron Threads',
            'slug' => 'saffron-threads',
            'category' => 'spices',
            'price' => 8.00,
            'stock' => 10,
            'stock_badge' => null,
        ]);

        $outOfStockProduct = Product::create([
            'name' => 'Cardamom Pods',
            'slug' => 'cardamom-pods',
            'category' => 'spices',
            'price' => 6.00,
            'stock' => 0,
            'stock_badge' => 'Out of Stock',
        ]);

        $soldOutBadgeProduct = Product::create([
            'name' => 'Rose Water',
            'slug' => 'rose-water',
            'category' => 'condiments',
            'price' => 3.50,
            'stock' => 5,
            'stock_badge' => 'Sold out',
        ]);

        // Kit A: Fully in-stock ingredients
        $inStockKit = RecipeKit::create([
            'name' => 'Royal Saffron Rice Kit',
            'slug' => 'royal-saffron-rice-kit',
            'price' => 18.00,
            'is_active' => true,
        ]);
        $inStockKit->products()->attach($inStockProduct1->id, ['quantity' => 1]);
        $inStockKit->products()->attach($inStockProduct2->id, ['quantity' => 1]);

        // Kit B: Has one ingredient with stock = 0
        $zeroStockKit = RecipeKit::create([
            'name' => 'Cardamom Kheer Kit',
            'slug' => 'cardamom-kheer-kit',
            'price' => 16.00,
            'is_active' => true,
        ]);
        $zeroStockKit->products()->attach($inStockProduct1->id, ['quantity' => 1]);
        $zeroStockKit->products()->attach($outOfStockProduct->id, ['quantity' => 1]);

        // Kit C: Has an ingredient with stock_badge containing 'out'
        $badgeOutKit = RecipeKit::create([
            'name' => 'Mithai Dessert Kit',
            'slug' => 'mithai-dessert-kit',
            'price' => 15.00,
            'is_active' => true,
        ]);
        $badgeOutKit->products()->attach($inStockProduct2->id, ['quantity' => 1]);
        $badgeOutKit->products()->attach($soldOutBadgeProduct->id, ['quantity' => 1]);

        // When in_stock=1 is applied, only Kit A must be returned
        $response = $this->get('/search?tab=kits&in_stock=1');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'royal-saffron-rice-kit')
        );

        // When in_stock is omitted, all 3 active kits are returned
        $allResponse = $this->get('/search?tab=kits');
        $allResponse->assertOk();
        $allResponse->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 3)
        );
    }

    public function test_search_recipe_kits_combining_category_in_stock_and_query_string(): void
    {
        $dairyInStock = Product::create([
            'name' => 'Fresh Buffalo Paneer',
            'slug' => 'fresh-buffalo-paneer',
            'category' => 'dairy',
            'price' => 8.00,
            'stock' => 15,
        ]);

        $dairyOutOfStock = Product::create([
            'name' => 'Artisanal Ghee',
            'slug' => 'artisanal-ghee',
            'category' => 'dairy',
            'price' => 14.00,
            'stock' => 0,
        ]);

        $kit1 = RecipeKit::create([
            'name' => 'Paneer Tikka Kit',
            'slug' => 'paneer-tikka-kit',
            'description' => 'Delicious paneer tikka with green chutney',
            'price' => 16.00,
            'is_active' => true,
        ]);
        $kit1->products()->attach($dairyInStock->id, ['quantity' => 1]);

        $kit2 = RecipeKit::create([
            'name' => 'Ghee Roast Kit',
            'slug' => 'ghee-roast-kit',
            'description' => 'Crispy paneer ghee roast',
            'price' => 20.00,
            'is_active' => true,
        ]);
        $kit2->products()->attach($dairyOutOfStock->id, ['quantity' => 1]);

        // 1. Search category=dairy & in_stock=1 -> returns only kit1
        $res1 = $this->get('/search?tab=kits&category=dairy&in_stock=1');
        $res1->assertOk();
        $res1->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'paneer-tikka-kit')
        );

        // 2. Search category=dairy & in_stock=0 (or omitted) & q=Ghee -> returns kit2
        $res2 = $this->get('/search?tab=kits&category=dairy&q=Ghee');
        $res2->assertOk();
        $res2->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', 'ghee-roast-kit')
        );

        // 3. Search category=dairy & in_stock=1 & q=Ghee -> returns 0 kits because kit2 is out of stock
        $res3 = $this->get('/search?tab=kits&category=dairy&in_stock=1&q=Ghee');
        $res3->assertOk();
        $res3->assertInertia(fn (Assert $page) => $page
            ->has('recipeKits.data', 0)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Objective 3: Reward Points Reconciliation Tests
    |--------------------------------------------------------------------------
    */

    public function test_regular_pickup_order_earns_points_based_on_total_without_delivery_fee(): void
    {
        $user = User::factory()->create(['reward_points' => 100]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 45.50,
            'delivery_fee' => 0.00,
            'total' => 45.50,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => (int) floor(45.50), // 45
        ]);

        // Verify points_earned in DB directly
        $dbPoints = DB::table('orders')->where('id', $order->id)->value('points_earned');
        $this->assertSame(45, $dbPoints);

        // Verify RewardPoint ledger sum for this order
        $ledgerPoints = (int) RewardPoint::where('order_id', $order->id)->where('user_id', $user->id)->sum('points');
        $this->assertSame(45, $ledgerPoints);

        // Verify user total points balance
        $this->assertSame(145, (int) $user->fresh()->reward_points);
    }

    public function test_delivery_order_earns_points_including_delivery_fee(): void
    {
        $user = User::factory()->create(['reward_points' => 50]);

        // Subtotal $30.00 + $4.99 delivery fee = $34.99 total -> floor(34.99) = 34 points
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 30.00,
            'delivery_fee' => 4.99,
            'total' => 34.99,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => (int) floor(34.99), // 34
        ]);

        $dbPoints = DB::table('orders')->where('id', $order->id)->value('points_earned');
        $this->assertSame(34, $dbPoints);

        $ledgerPoints = (int) RewardPoint::where('order_id', $order->id)->sum('points');
        $this->assertSame(34, $ledgerPoints);

        $this->assertSame(84, (int) $user->fresh()->reward_points);
    }

    public function test_checkout_conversion_sets_points_earned_matching_total(): void
    {
        $user = User::factory()->create(['reward_points' => 0]);
        $product = Product::factory()->create(['stock' => 10, 'price' => 40.00]);

        $checkout = Checkout::create([
            'fingerprint' => 'fp_checkout_conversion_points',
            'expires_at' => now()->addMinutes(30),
            'user_id' => $user->id,
            'customer_name' => 'Point Test Customer',
            'customer_email' => 'points@example.com',
            'subtotal' => 40.00,
            'delivery_fee' => 4.99,
            'total' => 44.99,
            'payment_method' => 'card',
            'fulfillment_type' => 'Home Delivery',
            'stripe_payment_id' => 'pi_points_test_123',
            'reserved_stock' => [$product->id => 1],
            'items' => [
                [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'unit_price' => 40.00,
                    'quantity' => 1,
                    'total_price' => 40.00,
                ],
            ],
        ]);

        $order = $checkout->convertToOrder('pi_points_test_123');

        $this->assertNotNull($order);
        $this->assertSame(44, (int) $order->points_earned);

        $dbPoints = DB::table('orders')->where('id', $order->id)->value('points_earned');
        $this->assertSame(44, $dbPoints);

        $this->assertSame(44, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame(44, (int) $user->fresh()->reward_points);
    }

    public function test_order_cancellation_via_cancel_and_restock_resets_points_earned_in_db(): void
    {
        $user = User::factory()->create(['reward_points' => 10]);
        $product = Product::factory()->create(['stock' => 5, 'price' => 25.00]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 25.00,
            'delivery_fee' => 4.99,
            'total' => 29.99,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => 29,
            'reserved_stock' => [$product->id => 1],
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 1,
            'total_price' => 25.00,
        ]);

        // Prior to cancellation:
        $this->assertSame(29, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));
        $this->assertSame(39, (int) $user->fresh()->reward_points);

        // Cancel and restock
        $cancelled = $order->cancelAndRestock('Customer requested cancellation', 29.99);
        $this->assertTrue($cancelled);

        // Direct DB verification: points_earned MUST be 0
        $dbPointsAfterCancel = DB::table('orders')->where('id', $order->id)->value('points_earned');
        $this->assertSame(0, $dbPointsAfterCancel);

        // Ledger sum MUST be 0
        $ledgerSumAfterCancel = (int) RewardPoint::where('order_id', $order->id)->sum('points');
        $this->assertSame(0, $ledgerSumAfterCancel);

        // User balance restored to 10
        $this->assertSame(10, (int) $user->fresh()->reward_points);
    }

    public function test_order_partial_refund_updates_points_earned_in_db(): void
    {
        $user = User::factory()->create(['reward_points' => 0]);

        // Total $54.99 ($50 subtotal + $4.99 delivery fee) -> 54 points
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 50.00,
            'delivery_fee' => 4.99,
            'total' => 54.99,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => 54,
        ]);

        $this->assertSame(54, (int) $user->fresh()->reward_points);
        $this->assertSame(54, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));

        // Partial refund of $20.00 -> remaining net total = $34.99 -> points_earned = floor(34.99) = 34
        $order->update(['refunded_total' => 20.00]);

        $dbPointsAfterRefund = DB::table('orders')->where('id', $order->id)->value('points_earned');
        $this->assertSame(34, $dbPointsAfterRefund);
        $this->assertSame(34, (int) $order->fresh()->points_earned);

        $ledgerSum = (int) RewardPoint::where('order_id', $order->id)->sum('points');
        $this->assertSame(34, $ledgerSum);

        $this->assertSame(34, (int) $user->fresh()->reward_points);
    }

    public function test_guest_order_points_earned_updates_in_db_safely_without_user(): void
    {
        // Guest order has user_id = null
        $order = Order::factory()->create([
            'user_id' => null,
            'customer_email' => 'guest@example.com',
            'subtotal' => 40.00,
            'delivery_fee' => 4.99,
            'total' => 44.99,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => 44,
        ]);

        $this->assertSame(44, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));

        // Cancel guest order
        $order->update(['status' => 'cancelled']);

        $this->assertSame(0, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));
        $this->assertSame(0, (int) $order->fresh()->points_earned);
        $this->assertSame(0, RewardPoint::where('order_id', $order->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Objective 4: Order Tracking Delivery Fee Tests
    |--------------------------------------------------------------------------
    */

    public function test_order_tracking_returns_delivery_fee_for_delivery_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-DEL1-1234',
            'subtotal' => 45.00,
            'delivery_fee' => 4.99,
            'total' => 49.99,
            'fulfillment_type' => 'Home Delivery',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->get('/orders/MM-20261021-DEL1-1234');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.delivery_fee', fn ($fee) => (float) $fee === 4.99)
            ->where('order.subtotal', fn ($sub) => (float) $sub === 45.00)
            ->where('order.total', fn ($tot) => (float) $tot === 49.99)
        );
    }

    public function test_order_tracking_returns_zero_delivery_fee_for_pickup_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-PICK-5678',
            'subtotal' => 32.00,
            'delivery_fee' => 0.00,
            'total' => 32.00,
            'fulfillment_type' => 'Store Pickup',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->get('/orders/MM-20261021-PICK-5678');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.delivery_fee', fn ($fee) => (float) $fee === 0.0)
            ->where('order.subtotal', fn ($sub) => (float) $sub === 32.00)
            ->where('order.total', fn ($tot) => (float) $tot === 32.00)
        );
    }

    public function test_order_tracking_returns_delivery_fee_for_guest_session_owner(): void
    {
        $order = Order::factory()->create([
            'user_id' => null,
            'order_number' => '#MM-20261021-GUEST-9999',
            'customer_email' => 'guest@example.com',
            'subtotal' => 20.00,
            'delivery_fee' => 4.99,
            'total' => 24.99,
            'fulfillment_type' => 'Home Delivery',
            'status' => 'confirmed',
        ]);

        // Without session placed_order_numbers, access is forbidden
        $forbidden = $this->get('/orders/MM-20261021-GUEST-9999');
        $forbidden->assertForbidden();

        // With session containing order_number, access is granted and delivery_fee is returned
        $response = $this->withSession([
            'placed_order_numbers' => ['#MM-20261021-GUEST-9999'],
        ])->get('/orders/MM-20261021-GUEST-9999');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.delivery_fee', fn ($fee) => (float) $fee === 4.99)
            ->where('order.total', fn ($tot) => (float) $tot === 24.99)
        );
    }

    public function test_order_tracking_handles_default_zero_delivery_fee(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-NULL-0000',
            'subtotal' => 50.00,
            'delivery_fee' => 0.00,
            'total' => 50.00,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->get('/orders/MM-20261021-NULL-0000');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.delivery_fee', fn ($fee) => (float) $fee === 0.0)
        );
    }

    public function test_multiple_successive_partial_refunds_accurately_reconcile_points_and_ledger(): void
    {
        $user = User::factory()->create(['reward_points' => 0]);

        // Total $100.00 -> 100 points
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 95.01,
            'delivery_fee' => 4.99,
            'total' => 100.00,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
            'points_earned' => 100,
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Refund 1: $30.00 refunded (net total $70.00 -> 70 points)
        $order->update(['refunded_total' => 30.00]);

        $this->assertSame(70, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));
        $this->assertSame(70, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame(70, (int) $user->fresh()->reward_points);

        // Refund 2: an additional $20.00 refunded (total refunded $50.00, net total $50.00 -> 50 points)
        $order->update(['refunded_total' => 50.00]);

        $this->assertSame(50, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));
        $this->assertSame(50, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame(50, (int) $user->fresh()->reward_points);

        // Refund 3: remaining $50.00 refunded (full refund, net total $0.00 -> 0 points)
        $order->update(['refunded_total' => 100.00]);

        $this->assertSame(0, (int) DB::table('orders')->where('id', $order->id)->value('points_earned'));
        $this->assertSame(0, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
        $this->assertSame(0, (int) $user->fresh()->reward_points);
    }
}
