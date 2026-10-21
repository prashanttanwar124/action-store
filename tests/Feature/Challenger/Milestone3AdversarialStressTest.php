<?php

namespace Tests\Feature\Challenger;

use App\Http\Controllers\AccountController;
use App\Http\Controllers\OrderController;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StripePayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use ReflectionMethod;
use Stripe\PaymentIntent;
use Tests\TestCase;

class Milestone3AdversarialStressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
    }

    /**
     * EMPIRICAL CHECK 1: Reordering items via getUserOrders emits kit-prefixed IDs
     * for recipe kits and numeric IDs for products, with strict type declarations.
     */
    public function test_reorder_items_distinguish_recipe_kits_and_products_with_exact_formats(): void
    {
        $user = User::factory()->create();

        $product1 = Product::factory()->create([
            'id' => 101,
            'name' => 'Basmati Rice Premium 5kg',
            'price' => 14.50,
            'stock' => 20,
        ]);

        $product2 = Product::factory()->create([
            'id' => 202,
            'name' => 'Desi Ghee 1L',
            'price' => 18.00,
            'stock' => 15,
        ]);

        $kit1 = RecipeKit::create([
            'name' => 'Paneer Butter Masala Kit',
            'slug' => 'paneer-butter-masala-kit',
            'price' => 24.99,
            'is_active' => true,
        ]);

        $kit2 = RecipeKit::create([
            'name' => 'Chole Bhature Gourmet Kit',
            'slug' => 'chole-bhature-gourmet-kit',
            'price' => 21.50,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-9901-C1D2',
            'status' => 'completed',
            'total' => 78.99,
            'subtotal' => 78.99,
            'delivery_fee' => 0.00,
        ]);

        // Standard Product 1 item
        $itemProd1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'recipe_kit_id' => null,
            'name' => $product1->name,
            'unit_price' => 14.50,
            'quantity' => 1,
            'total_price' => 14.50,
        ]);

        // Recipe Kit 1 item with recipe_kit_id populated
        $itemKit1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'recipe_kit_id' => $kit1->id,
            'name' => $kit1->name,
            'unit_price' => 24.99,
            'quantity' => 1,
            'total_price' => 24.99,
        ]);

        // Recipe Kit 2 item legacy with null recipe_kit_id and null product_id, resolved by name
        $itemKit2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'recipe_kit_id' => null,
            'name' => $kit2->name,
            'unit_price' => 21.50,
            'quantity' => 1,
            'total_price' => 21.50,
        ]);

        // Legacy recipe kit whose database record has been deleted
        $itemDeletedKit = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'recipe_kit_id' => null,
            'name' => 'Discontinued Samosa Feast Kit',
            'unit_price' => 18.00,
            'quantity' => 1,
            'total_price' => 18.00,
        ]);

        $response = $this->actingAs($user)->get('/reorder');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders.0.items', 4)
            // Product 1 assertions: numeric ID, type product, isRecipeKit false
            ->where('dbOrders.0.items.0.id', $product1->id)
            ->where('dbOrders.0.items.0.type', 'product')
            ->where('dbOrders.0.items.0.isRecipeKit', false)
            ->where('dbOrders.0.items.0.slug', null)
            // Kit 1 assertions: kit-prefixed ID, type recipe-kit, isRecipeKit true, slug populated
            ->where('dbOrders.0.items.1.id', "kit-{$kit1->id}")
            ->where('dbOrders.0.items.1.type', 'recipe-kit')
            ->where('dbOrders.0.items.1.isRecipeKit', true)
            ->where('dbOrders.0.items.1.slug', 'paneer-butter-masala-kit')
            // Kit 2 assertions (name match fallback): kit-prefixed ID, type recipe-kit, isRecipeKit true
            ->where('dbOrders.0.items.2.id', "kit-{$kit2->id}")
            ->where('dbOrders.0.items.2.type', 'recipe-kit')
            ->where('dbOrders.0.items.2.isRecipeKit', true)
            ->where('dbOrders.0.items.2.slug', 'chole-bhature-gourmet-kit')
            // Deleted Kit assertions: kit-prefixed with row ID, type recipe-kit, NEVER product
            ->where('dbOrders.0.items.3.id', "kit-{$itemDeletedKit->id}")
            ->where('dbOrders.0.items.3.type', 'recipe-kit')
            ->where('dbOrders.0.items.3.isRecipeKit', true)
            ->where('dbOrders.0.items.3.slug', null)
        );

        // Verify product ID is an integer
        $orders = $response->viewData('page')['props']['dbOrders'];
        $this->assertIsInt($orders[0]['items'][0]['id']);
        $this->assertSame(101, $orders[0]['items'][0]['id']);

        // Verify unauthenticated access returns empty array for getUserOrders
        Auth::logout();
        $accountController = app(AccountController::class);
        $method = new ReflectionMethod($accountController, 'getUserOrders');
        $method->setAccessible(true);
        $this->assertSame([], $method->invoke($accountController));
    }

    /**
     * EMPIRICAL CHECK 2: chargeMatches() collision resistance for distinct kits with identical price/qty.
     */
    public function test_charge_matches_prevents_collision_between_different_recipe_kits(): void
    {
        $user = User::factory()->create();
        $controller = app(OrderController::class);
        $method = new ReflectionMethod($controller, 'chargeMatches');
        $method->setAccessible(true);

        $checkout = Checkout::create([
            'fingerprint' => 'test_fingerprint_charge_match_1',
            'expires_at' => now()->addMinutes(30),
            'user_id' => $user->id,
            'customer_name' => 'Challenger User',
            'customer_email' => 'challenger@example.com',
            'subtotal' => 29.99,
            'delivery_fee' => 0.00,
            'total' => 29.99,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'stripe_payment_id' => 'pi_test_collision_check',
            'reserved_stock' => [1 => 1],
            'items' => [
                [
                    'product_id' => null,
                    'recipe_kit_id' => 10,
                    'name' => 'Royal Biryani Kit',
                    'quantity' => 1,
                    'unit_price' => 29.99,
                ],
            ],
        ]);

        // Attack scenario: Different kit ID (20 instead of 10) but identical price, quantity, and subtotal
        $attributesCollidingPrice = [
            'total' => 29.99,
            'subtotal' => 29.99,
            'delivery_fee' => 0.00,
            'reserved_stock' => [1 => 1],
            'items' => [
                [
                    'product_id' => null,
                    'recipe_kit_id' => 20,
                    'name' => 'Mughlai Korma Kit',
                    'quantity' => 1,
                    'unit_price' => 29.99,
                ],
            ],
        ];

        $this->assertFalse(
            $method->invoke($controller, $checkout, $attributesCollidingPrice),
            'chargeMatches must reject distinct recipe kits even when price and quantity match exactly.'
        );

        // Cross-type collision test: Product ID 10 vs Recipe Kit ID 10 with same price
        $attributesProductInsteadOfKit = [
            'total' => 29.99,
            'subtotal' => 29.99,
            'delivery_fee' => 0.00,
            'reserved_stock' => [1 => 1],
            'items' => [
                [
                    'product_id' => 10,
                    'recipe_kit_id' => null,
                    'name' => 'Royal Biryani Kit',
                    'quantity' => 1,
                    'unit_price' => 29.99,
                ],
            ],
        ];

        $this->assertFalse(
            $method->invoke($controller, $checkout, $attributesProductInsteadOfKit),
            'chargeMatches must reject line items where product_id is set instead of recipe_kit_id.'
        );
    }

    /**
     * EMPIRICAL CHECK 2 (Part B): chargeMatches() deterministic sorting with arbitrary line permutations.
     */
    public function test_charge_matches_sorts_lines_deterministically_across_permutations(): void
    {
        $user = User::factory()->create();
        $controller = app(OrderController::class);
        $method = new ReflectionMethod($controller, 'chargeMatches');
        $method->setAccessible(true);

        $lineItems = [
            ['product_id' => 1, 'recipe_kit_id' => null, 'name' => 'Atta 10kg', 'quantity' => 1, 'unit_price' => 14.99],
            ['product_id' => 2, 'recipe_kit_id' => null, 'name' => 'Basmati Rice', 'quantity' => 2, 'unit_price' => 12.50],
            ['product_id' => null, 'recipe_kit_id' => 10, 'name' => 'Kit Alpha', 'quantity' => 1, 'unit_price' => 19.99],
            ['product_id' => null, 'recipe_kit_id' => 15, 'name' => 'Kit Beta', 'quantity' => 1, 'unit_price' => 22.00],
        ];

        $subtotal = 14.99 + (2 * 12.50) + 19.99 + 22.00; // 81.98

        $checkout = Checkout::create([
            'fingerprint' => 'test_fingerprint_perm_check',
            'expires_at' => now()->addMinutes(30),
            'user_id' => $user->id,
            'customer_name' => 'Permutation Tester',
            'customer_email' => 'perm@example.com',
            'subtotal' => $subtotal,
            'delivery_fee' => 4.99,
            'total' => $subtotal + 4.99,
            'payment_method' => 'card',
            'fulfillment_type' => 'Home Delivery',
            'stripe_payment_id' => 'pi_test_perm',
            'reserved_stock' => [1 => 1, 2 => 2, 5 => 1],
            'items' => $lineItems,
        ]);

        // Permutation 1: Reversed line order
        $attributesReversed = [
            'total' => $subtotal + 4.99,
            'subtotal' => $subtotal,
            'delivery_fee' => 4.99,
            'reserved_stock' => [1 => 1, 2 => 2, 5 => 1],
            'items' => array_reverse($lineItems),
        ];

        $this->assertTrue(
            $method->invoke($controller, $checkout, $attributesReversed),
            'chargeMatches must match reversed line orders deterministically.'
        );

        // Permutation 2: Interleaved line order
        $interleaved = [$lineItems[2], $lineItems[0], $lineItems[3], $lineItems[1]];
        $attributesInterleaved = [
            'total' => $subtotal + 4.99,
            'subtotal' => $subtotal,
            'delivery_fee' => 4.99,
            'reserved_stock' => [1 => 1, 2 => 2, 5 => 1],
            'items' => $interleaved,
        ];

        $this->assertTrue(
            $method->invoke($controller, $checkout, $attributesInterleaved),
            'chargeMatches must match interleaved line orders deterministically.'
        );

        // Adversarial case: Total is identical, but delivery fee and subtotal swapped
        $attributesFeeSwapped = [
            'total' => $subtotal + 4.99,
            'subtotal' => $subtotal + 4.99,
            'delivery_fee' => 0.00,
            'reserved_stock' => [1 => 1, 2 => 2, 5 => 1],
            'items' => $lineItems,
        ];

        $this->assertFalse(
            $method->invoke($controller, $checkout, $attributesFeeSwapped),
            'chargeMatches must reject when subtotal and delivery fee diverge even if total matches.'
        );
    }

    /**
     * EMPIRICAL CHECK 3: Custom pickup times map to encompassing slots and enforce slot capacity limits.
     */
    public function test_custom_pickup_time_slot_mapping_and_capacity_exhaustion(): void
    {
        $today = now()->toDateString();
        $setting = StoreSetting::current();
        $setting->update([
            'max_orders_per_slot' => 2,
            'is_pickup_active' => true,
            'pickup_slot_start_time' => '09:00',
            'pickup_slot_end_time' => '21:00',
            'pickup_slot_duration_minutes' => 60,
        ]);

        $controller = app(OrderController::class);
        $findSlotMethod = new ReflectionMethod($controller, 'findEncompassingPickupSlot');
        $findSlotMethod->setAccessible(true);

        $slots = (array) $setting->available_pickup_slots;

        // Verify boundary time mapping
        // 17:00 (exact start of 5-6 PM window)
        $slot1700 = $findSlotMethod->invoke($controller, '17:00', $slots);
        $this->assertNotNull($slot1700);
        $this->assertSame('5:00 PM – 6:00 PM', $slot1700['label']);

        // 17:30 (middle of window)
        $slot1730 = $findSlotMethod->invoke($controller, '17:30', $slots);
        $this->assertNotNull($slot1730);
        $this->assertSame('5:00 PM – 6:00 PM', $slot1730['label']);

        // 17:59 (boundary before next window)
        $slot1759 = $findSlotMethod->invoke($controller, '17:59', $slots);
        $this->assertNotNull($slot1759);
        $this->assertSame('5:00 PM – 6:00 PM', $slot1759['label']);

        // 18:00 (exact start of 6-7 PM window)
        $slot1800 = $findSlotMethod->invoke($controller, '18:00', $slots);
        $this->assertNotNull($slot1800);
        $this->assertSame('6:00 PM – 7:00 PM', $slot1800['label']);

        // 21:00 (exact boundary of final active slot)
        $slot2100 = $findSlotMethod->invoke($controller, '21:00', $slots);
        $this->assertNotNull($slot2100);
        $this->assertSame('8:00 PM – 9:00 PM', $slot2100['label']);

        // Now test capacity saturation: Place 2 orders in 5:00 PM – 6:00 PM window
        // Order 1: Custom time order (17:15)
        Order::factory()->create([
            'pickup_slot' => "{$today} · 5:15 PM (Custom Time)",
            'status' => 'confirmed',
        ]);

        // Order 2: Preset slot order
        Order::factory()->create([
            'pickup_slot' => "{$today} · 5:00 PM – 6:00 PM",
            'status' => 'confirmed',
        ]);

        // Capacity is now 2/2 for "{$today} · 5:00 PM – 6:00 PM"
        $this->assertSame(2, Order::bookedInSlot("{$today} · 5:00 PM – 6:00 PM"));

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 50, 'price' => 15.00]);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
        });

        // Attempt 3rd order with custom pickup time 17:45 -> MUST BE REJECTED (422)
        $payloadCustom = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => $today,
            'pickup_time' => '17:45',
            'payment_method' => 'card',
        ];

        $resCustom = $this->actingAs($user)->postJson('/checkout', $payloadCustom);
        $resCustom->assertStatus(422);
        $resCustom->assertJsonValidationErrors(['pickup_time']);
        $this->assertSame('slot_full', $resCustom->json('errors.error_code.0'));
        $this->assertStringContainsString('capacity limit', $resCustom->json('errors.pickup_time.0'));

        // Attempt 4th order with preset slot "5:00 PM – 6:00 PM" -> MUST ALSO BE REJECTED (422)
        $payloadSlot = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'slot',
            'pickup_date' => $today,
            'pickup_slot' => '5:00 PM – 6:00 PM',
            'payment_method' => 'card',
        ];

        $resSlot = $this->actingAs($user)->postJson('/checkout', $payloadSlot);
        $resSlot->assertStatus(422);
        $resSlot->assertJsonValidationErrors(['pickup_slot']);
        $this->assertSame('slot_full', $resSlot->json('errors.error_code.0'));

        // Order with custom time in adjacent open window (18:30 in 6:00 PM – 7:00 PM) -> SUCCEEDS (201)
        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('createIntentFor')->once()->andReturn(
                PaymentIntent::constructFrom(['id' => 'pi_test_slot_ok', 'status' => 'requires_payment_method', 'client_secret' => 'sec_ok'])
            );
        });

        $payloadOpenSlot = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => $today,
            'pickup_time' => '18:30',
            'payment_method' => 'card',
        ];

        $resOpen = $this->actingAs($user)->postJson('/checkout', $payloadOpenSlot);
        $resOpen->assertStatus(201);
        $this->assertDatabaseHas('checkouts', [
            'capacity_slot' => "{$today} · 6:00 PM – 7:00 PM",
        ]);
    }

    /**
     * EMPIRICAL CHECK 3 (Part B): Order cancellation restores capacity slot availability.
     */
    public function test_custom_pickup_capacity_restored_when_order_is_cancelled(): void
    {
        $today = now()->toDateString();
        $setting = StoreSetting::current();
        $setting->update([
            'max_orders_per_slot' => 1,
            'is_pickup_active' => true,
        ]);

        $order = Order::factory()->create([
            'pickup_slot' => "{$today} · 5:30 PM (Custom Time)",
            'status' => 'confirmed',
        ]);

        $this->assertSame(1, Order::bookedInSlot("{$today} · 5:00 PM – 6:00 PM"));

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
        });

        // When capacity is full (1/1), order is rejected
        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => $today,
            'pickup_time' => '17:45',
            'payment_method' => 'card',
        ];

        $this->actingAs($user)->postJson('/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_time']);

        // Cancel the order
        $order->update(['status' => 'cancelled']);
        $this->assertSame(0, Order::bookedInSlot("{$today} · 5:00 PM – 6:00 PM"));

        // Capacity is restored, new order succeeds
        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('createIntentFor')->once()->andReturn(
                PaymentIntent::constructFrom(['id' => 'pi_restored', 'status' => 'requires_payment_method', 'client_secret' => 'sec_restored'])
            );
        });

        $this->actingAs($user)->postJson('/checkout', $payload)->assertStatus(201);
    }

    /**
     * EMPIRICAL CHECK 4: Cart stock check bounds, element validations, and rate limiting.
     */
    public function test_cart_stock_check_throttling_and_boundary_conditions(): void
    {
        // 1. Boundary: Empty array rejects with 422
        $resZero = $this->postJson('/cart/stock-check', ['ids' => []]);
        $resZero->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // 2. Boundary: Exactly 50 items succeeds with 200
        $fiftyIds = array_map(fn ($i) => "item-{$i}", range(1, 50));
        $resFifty = $this->postJson('/cart/stock-check', ['ids' => $fiftyIds]);
        $resFifty->assertOk();

        // 3. Boundary: Exactly 51 items rejects with 422
        $fiftyOneIds = array_map(fn ($i) => "item-{$i}", range(1, 51));
        $resFiftyOne = $this->postJson('/cart/stock-check', ['ids' => $fiftyOneIds]);
        $resFiftyOne->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // 4. Element stress: Empty string, whitespace-only, or non-scalar element rejected
        $resEmptyString = $this->postJson('/cart/stock-check', ['ids' => ['']]);
        $resEmptyString->assertStatus(422)->assertJsonValidationErrors(['ids.0']);

        $resWhitespace = $this->postJson('/cart/stock-check', ['ids' => ['   ']]);
        $resWhitespace->assertStatus(422)->assertJsonValidationErrors(['ids.0']);

        $resNestedArray = $this->postJson('/cart/stock-check', ['ids' => [['nested' => 'id']]]);
        $resNestedArray->assertStatus(422)->assertJsonValidationErrors(['ids.0']);

        // 5. Length stress: 120 chars succeeds, 121 chars rejects
        $str120 = str_repeat('a', 120);
        $res120 = $this->postJson('/cart/stock-check', ['ids' => [$str120]]);
        $res120->assertOk();

        $str121 = str_repeat('b', 121);
        $res121 = $this->postJson('/cart/stock-check', ['ids' => [$str121]]);
        $res121->assertStatus(422)->assertJsonValidationErrors(['ids.0']);

        // 6. Rate limiting: 60 requests within 1 minute allowed, 61st triggers 429
        RateLimiter::clear(sha1('cart.stock-check|127.0.0.1'));
        Cache::flush();

        for ($i = 1; $i <= 60; $i++) {
            $resp = $this->postJson('/cart/stock-check', ['ids' => ['test-item']]);
            $this->assertNotEquals(429, $resp->status(), "Request #{$i} was unexpectedly throttled.");
        }

        $throttled = $this->postJson('/cart/stock-check', ['ids' => ['test-item']]);
        $throttled->assertStatus(429);
        $this->assertTrue(
            $throttled->headers->has('Retry-After') || $throttled->headers->has('X-RateLimit-Reset'),
            'HTTP 429 response must provide rate limit retry headers.'
        );
    }
}
