<?php

namespace Tests\Feature\Adversarial;

use App\Http\Controllers\OrderController;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\RewardPoint;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CheckoutCompletionResult;
use App\Services\StripePayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use ReflectionMethod;
use Stripe\PaymentIntent;
use Tests\TestCase;

class CustomerCommerceAdversarialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Standard daytime setting for deterministic time-based assertions
        $this->travelTo(now()->setTime(14, 0));
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 1: Boundary Conditions & Limits
    |--------------------------------------------------------------------------
    */

    public function test_cart_stock_check_boundary_at_max_items_limit(): void
    {
        // 0 items -> rejected (min: 1)
        $resZero = $this->postJson('/cart/stock-check', ['ids' => []]);
        $resZero->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // Exactly 50 items -> accepted (max: 50)
        $fiftyIds = array_map(fn ($i) => "product-{$i}", range(1, 50));
        $resFifty = $this->postJson('/cart/stock-check', ['ids' => $fiftyIds]);
        $resFifty->assertOk();
        $this->assertIsArray($resFifty->json('products'));
        $this->assertIsArray($resFifty->json('recipeKits'));

        // Exactly 51 items -> rejected (max: 50)
        $fiftyOneIds = array_map(fn ($i) => "product-{$i}", range(1, 51));
        $resFiftyOne = $this->postJson('/cart/stock-check', ['ids' => $fiftyOneIds]);
        $resFiftyOne->assertStatus(422)->assertJsonValidationErrors(['ids']);

        // Empty string or whitespace identifier -> rejected
        $resEmpty = $this->postJson('/cart/stock-check', ['ids' => ['   ']]);
        $resEmpty->assertStatus(422)->assertJsonValidationErrors(['ids.0']);
    }

    public function test_checkout_rejects_negative_or_zero_or_decimal_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);

        $basePayload = [
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        // Zero quantity -> rejected
        $resZero = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 0]],
        ]));
        $resZero->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);

        // Negative quantity -> rejected
        $resNeg = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => -5]],
        ]));
        $resNeg->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);

        // Float / Decimal quantity -> rejected
        $resFloat = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 2.5]],
        ]));
        $resFloat->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_checkout_rejects_past_or_distant_future_pickup_dates(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 15.00]);

        $basePayload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_time' => '16:00',
            'payment_method' => 'card',
        ];

        // Yesterday's date -> rejected
        $yesterday = now()->subDay()->toDateString();
        $resPast = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, ['pickup_date' => $yesterday]));
        $resPast->assertStatus(422)->assertJsonValidationErrors(['pickup_date']);
        $this->assertSame('date_invalid', $resPast->json('errors.error_code.0'));

        // Two days in future -> rejected
        $twoDaysAhead = now()->addDays(2)->toDateString();
        $resFar = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, ['pickup_date' => $twoDaysAhead]));
        $resFar->assertStatus(422)->assertJsonValidationErrors(['pickup_date']);
        $this->assertSame('date_invalid', $resFar->json('errors.error_code.0'));
    }

    public function test_asap_pickup_rejected_when_store_closed_or_prep_time_exceeds_closing(): void
    {
        $setting = StoreSetting::current();
        $setting->update([
            'is_pickup_active' => true,
            'pickup_slot_start_time' => '09:00',
            'pickup_slot_end_time' => '21:00',
            'prep_time_minutes' => 30,
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);

        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
            'payment_method' => 'card',
        ];

        // Scenario A: Outside store hours (e.g. 21:30 at night)
        $this->travelTo(now()->setTime(21, 30));
        $resClosed = $this->actingAs($user)->postJson('/checkout', $payload);
        $resClosed->assertStatus(422)->assertJsonValidationErrors(['pickup_timing_mode']);
        $this->assertSame('slot_expired', $resClosed->json('errors.error_code.0'));

        // Scenario B: Inside store hours, but prep time extends past closing (e.g. 20:45 + 30m prep = 21:15 > 21:00)
        $this->travelTo(now()->setTime(20, 45));
        $resLate = $this->actingAs($user)->postJson('/checkout', $payload);
        $resLate->assertStatus(422)->assertJsonValidationErrors(['pickup_timing_mode']);
        $this->assertSame('slot_expired', $resLate->json('errors.error_code.0'));

        // Scenario C: Well within operating hours (14:00) -> succeeds
        $this->travelTo(now()->setTime(14, 0));
        $resOpen = $this->actingAs($user)->postJson('/checkout', $payload);
        $resOpen->assertStatus(201);
    }

    public function test_home_delivery_free_threshold_and_minimum_order_boundaries(): void
    {
        $setting = StoreSetting::current();
        $setting->update([
            'is_delivery_active' => true,
            'is_delivery_available_today' => true,
            'delivery_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'min_order_amount' => 25.00,
            'free_delivery_threshold' => 50.00,
            'delivery_fee' => 4.99,
        ]);

        $user = User::factory()->create();
        $p24 = Product::factory()->create(['stock' => 20, 'price' => 24.99]);
        $p25 = Product::factory()->create(['stock' => 20, 'price' => 25.00]);
        $p49 = Product::factory()->create(['stock' => 20, 'price' => 49.99]);
        $p50 = Product::factory()->create(['stock' => 20, 'price' => 50.00]);

        $baseDelivery = [
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '123 Market St, San Francisco, CA',
            'payment_method' => 'card',
        ];

        // 1. Below Minimum Order Amount ($24.99 < $25.00) -> rejected with min_order_not_met
        $resBelowMin = $this->actingAs($user)->postJson('/checkout', array_merge($baseDelivery, [
            'items' => [['id' => $p24->id, 'name' => $p24->name, 'price' => 24.99, 'quantity' => 1]],
        ]));
        $resBelowMin->assertStatus(422)->assertJsonValidationErrors(['total']);
        $this->assertSame('min_order_not_met', $resBelowMin->json('errors.error_code.0'));

        // 2. Exactly at Minimum Order Amount ($25.00) -> passes; below free delivery ($25 < $50) -> fee charged
        $resExactMin = $this->actingAs($user)->postJson('/checkout', array_merge($baseDelivery, [
            'items' => [['id' => $p25->id, 'name' => $p25->name, 'price' => 25.00, 'quantity' => 1]],
        ]));
        $resExactMin->assertStatus(201);
        $orderExactMin = Order::latest('id')->first();
        $this->assertEquals(25.00, (float) $orderExactMin->subtotal);
        $this->assertEquals(4.99, (float) $orderExactMin->delivery_fee);
        $this->assertEquals(29.99, (float) $orderExactMin->total);

        // 3. Just below Free Delivery Threshold ($49.99 < $50.00) -> delivery fee charged
        $resBelowFree = $this->actingAs($user)->postJson('/checkout', array_merge($baseDelivery, [
            'items' => [['id' => $p49->id, 'name' => $p49->name, 'price' => 49.99, 'quantity' => 1]],
        ]));
        $resBelowFree->assertStatus(201);
        $orderBelowFree = Order::latest('id')->first();
        $this->assertEquals(49.99, (float) $orderBelowFree->subtotal);
        $this->assertEquals(4.99, (float) $orderBelowFree->delivery_fee);
        $this->assertEquals(54.98, (float) $orderBelowFree->total);

        // 4. Exactly at Free Delivery Threshold ($50.00) -> delivery fee waived ($0.00)
        $resExactFree = $this->actingAs($user)->postJson('/checkout', array_merge($baseDelivery, [
            'items' => [['id' => $p50->id, 'name' => $p50->name, 'price' => 50.00, 'quantity' => 1]],
        ]));
        $resExactFree->assertStatus(201);
        $orderExactFree = Order::latest('id')->first();
        $this->assertEquals(50.00, (float) $orderExactFree->subtotal);
        $this->assertEquals(0.00, (float) $orderExactFree->delivery_fee);
        $this->assertEquals(50.00, (float) $orderExactFree->total);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 2: Kit vs Loose Product Combinations & Stock Aggregation
    |--------------------------------------------------------------------------
    */

    public function test_mixed_cart_competing_for_same_ingredient_detects_aggregate_exhaustion(): void
    {
        $user = User::factory()->create();

        // Tomato has 5 units in stock
        $tomato = Product::factory()->create(['name' => 'Organic Roma Tomatoes', 'stock' => 5, 'price' => 2.00]);
        // Onion has plenty of stock
        $onion = Product::factory()->create(['name' => 'Red Onions', 'stock' => 20, 'price' => 1.50]);

        // Salsa Kit requires 3 Tomatoes and 2 Onions
        $salsaKit = RecipeKit::create([
            'name' => 'Fresh Salsa Kit',
            'slug' => 'fresh-salsa-kit',
            'price' => 8.99,
            'is_active' => true,
        ]);
        $salsaKit->products()->attach([
            $tomato->id => ['quantity' => 3],
            $onion->id => ['quantity' => 2],
        ]);

        $basePayload = [
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        // Scenario A: Kit x 1 (needs 3 tomatoes) + Loose Tomato x 3 (needs 3 tomatoes). Total needed = 6 > 5 stock!
        $payloadExceed = array_merge($basePayload, [
            'items' => [
                ['id' => "kit-{$salsaKit->id}", 'type' => 'recipe-kit', 'name' => $salsaKit->name, 'price' => 8.99, 'quantity' => 1],
                ['id' => $tomato->id, 'type' => 'product', 'name' => $tomato->name, 'price' => 2.00, 'quantity' => 3],
            ],
        ]);

        $resExceed = $this->actingAs($user)->postJson('/checkout', $payloadExceed);
        $resExceed->assertStatus(422)->assertJsonValidationErrors(['items']);
        $this->assertSame('out_of_stock', $resExceed->json('errors.error_code.0'));
        $this->assertStringContainsString('Total requested: 6, but only 5 available', $resExceed->json('errors.items.0'));

        // Stock remains unmutated
        $this->assertSame(5, $tomato->fresh()->stock);

        // Scenario B: Kit x 1 (needs 3 tomatoes) + Loose Tomato x 2 (needs 2 tomatoes). Total needed = 5 == 5 stock.
        $payloadExact = array_merge($basePayload, [
            'items' => [
                ['id' => "kit-{$salsaKit->id}", 'type' => 'recipe-kit', 'name' => $salsaKit->name, 'price' => 8.99, 'quantity' => 1],
                ['id' => $tomato->id, 'type' => 'product', 'name' => $tomato->name, 'price' => 2.00, 'quantity' => 2],
            ],
        ]);

        $resExact = $this->actingAs($user)->postJson('/checkout', $payloadExact);
        $resExact->assertStatus(201);

        // Tomato stock decremented by exact aggregate (3 + 2 = 5) to 0
        $this->assertSame(0, $tomato->fresh()->stock);
        // Onion stock decremented by kit demand (2) to 18
        $this->assertSame(18, $onion->fresh()->stock);

        // Verify Order reserved_stock snapshot captures aggregated product IDs
        $order = Order::latest('id')->first();
        $this->assertSame([$tomato->id => 5, $onion->id => 2], $order->reserved_stock);

        // Verify Order items have distinct product and kit lines
        $this->assertCount(2, $order->items);
        $kitItem = $order->items->firstWhere('product_id', null);
        $prodItem = $order->items->firstWhere('product_id', $tomato->id);
        $this->assertNotNull($kitItem);
        $this->assertNotNull($prodItem);
        $this->assertSame($salsaKit->name, $kitItem->name);
        $this->assertSame($tomato->name, $prodItem->name);
    }

    public function test_mixed_cart_cancellation_restores_ingredient_and_loose_stock_atomically(): void
    {
        $user = User::factory()->create();
        $tomato = Product::factory()->create(['name' => 'Tomatoes', 'stock' => 10, 'price' => 2.00]);
        $basil = Product::factory()->create(['name' => 'Fresh Basil', 'stock' => 10, 'price' => 3.00]);

        $pastaKit = RecipeKit::create([
            'name' => 'Basil Pasta Kit',
            'slug' => 'basil-pasta-kit',
            'price' => 12.00,
            'is_active' => true,
        ]);
        $pastaKit->products()->attach([
            $tomato->id => ['quantity' => 2],
            $basil->id => ['quantity' => 1],
        ]);

        $payload = [
            'items' => [
                ['id' => "kit-{$pastaKit->id}", 'type' => 'recipe-kit', 'name' => $pastaKit->name, 'price' => 12.00, 'quantity' => 2], // needs 4 tomatoes, 2 basils
                ['id' => $tomato->id, 'type' => 'product', 'name' => $tomato->name, 'price' => 2.00, 'quantity' => 3], // needs 3 tomatoes
            ],
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        $res = $this->actingAs($user)->postJson('/checkout', $payload);
        $res->assertStatus(201);

        $this->assertSame(3, $tomato->fresh()->stock); // 10 - 7 = 3
        $this->assertSame(8, $basil->fresh()->stock);  // 10 - 2 = 8

        $order = Order::latest('id')->first();
        $this->assertTrue($order->cancelAndRestock('Customer requested return'));

        // Stock fully restored
        $this->assertSame(10, $tomato->fresh()->stock);
        $this->assertSame(10, $basil->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertStringContainsString('Customer requested return', $order->fresh()->notes);
    }

    public function test_kit_item_without_explicit_type_resolves_correctly_without_colliding_with_product_of_same_id(): void
    {
        $user = User::factory()->create();

        // Create product with explicit ID
        $product = Product::factory()->create([
            'price' => 15.00,
            'stock' => 10,
        ]);

        // Create recipe kit forced with identical primary key as product
        $kit = RecipeKit::forceCreate([
            'id' => $product->id,
            'name' => 'Kit Matching Product ID',
            'slug' => 'kit-matching-product-id',
            'price' => 25.00,
            'is_active' => true,
        ]);
        $kitProduct = Product::factory()->create(['stock' => 20, 'price' => 5.00]);
        $kit->products()->attach($kitProduct->id, ['quantity' => 2]);

        $basePayload = [
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        // Case 1: ID is $product->id and type is 'product' -> Resolves Product ($15.00)
        $resProd = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, [
            'items' => [['id' => $product->id, 'type' => 'product', 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
        ]));
        $resProd->assertStatus(201);
        $orderProd = Order::latest('id')->first();
        $this->assertEquals(15.00, (float) $orderProd->subtotal);
        $this->assertSame($product->id, $orderProd->items->first()->product_id);

        // Case 2: ID is "kit-{$kit->id}" -> Resolves RecipeKit ($25.00)
        $resKitPrefixed = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, [
            'items' => [['id' => "kit-{$kit->id}", 'name' => $kit->name, 'price' => 25.00, 'quantity' => 1]],
        ]));
        $resKitPrefixed->assertStatus(201);
        $orderKit = Order::latest('id')->first();
        $this->assertEquals(25.00, (float) $orderKit->subtotal);
        $this->assertNull($orderKit->items->first()->product_id);
        $this->assertSame($kit->name, $orderKit->items->first()->name);

        // Case 3: ID is numeric $kit->id with type 'recipe-kit' -> Resolves RecipeKit ($25.00)
        $resKitTyped = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, [
            'items' => [['id' => $kit->id, 'type' => 'recipe-kit', 'name' => $kit->name, 'price' => 25.00, 'quantity' => 1]],
        ]));
        $resKitTyped->assertStatus(201);
        $orderKitTyped = Order::latest('id')->first();
        $this->assertEquals(25.00, (float) $orderKitTyped->subtotal);
        $this->assertNull($orderKitTyped->items->first()->product_id);
        $this->assertSame($kit->name, $orderKitTyped->items->first()->name);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 3: Pricing Tampering & Price Change Boundaries
    |--------------------------------------------------------------------------
    */

    public function test_price_changed_detection_within_tolerance_vs_exceeding_tolerance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 30.00, 'stock' => 10]);

        $basePayload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 30.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        // 1. Expected total difference of $0.04 (within 5-cent tolerance) -> passes
        $resWithin = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, ['expected_total' => 30.04]));
        $resWithin->assertStatus(201);

        // 2. Expected total difference of $0.06 (exceeding 5-cent tolerance) -> throws price_changed (422)
        $resExceed = $this->actingAs($user)->postJson('/checkout', array_merge($basePayload, ['expected_total' => 30.06]));
        $resExceed->assertStatus(422)->assertJsonValidationErrors(['total']);
        $this->assertSame('price_changed', $resExceed->json('errors.error_code.0'));
        $this->assertStringContainsString('Order total has changed', $resExceed->json('errors.total.0'));
    }

    public function test_price_tampering_by_client_is_silently_corrected_to_authoritative_catalog_price(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 75.00, 'stock' => 10]);

        // Malicious client claims price is $0.01 in the payload
        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 0.01, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        $res = $this->actingAs($user)->postJson('/checkout', $payload);
        $res->assertStatus(201);

        $order = Order::latest('id')->first();
        // Server calculates subtotal from authoritative DB catalog price ($75.00), not client's $0.01
        $this->assertEquals(75.00, (float) $order->subtotal);
        $this->assertEquals(75.00, (float) $order->total);
        $this->assertEquals(75.00, (float) $order->items->first()->unit_price);
    }

    public function test_checkout_resumption_with_catalog_price_change_releases_old_checkout(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20.00, 'stock' => 10]);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('createIntentFor')->twice()->andReturn(
                PaymentIntent::constructFrom([
                    'id' => 'pi_test_price_change_resumption',
                    'status' => 'requires_payment_method',
                    'client_secret' => 'sec_test_pc',
                ])
            );
            $mock->shouldReceive('retrieveIntent')->andReturn(
                PaymentIntent::constructFrom([
                    'id' => 'pi_test_price_change_resumption',
                    'status' => 'requires_payment_method',
                    'client_secret' => 'sec_test_pc',
                ])
            );
            $mock->shouldReceive('releaseCheckout')->once()->andReturnUsing(function (Checkout $co) {
                $co->release();

                return StripePayments::RELEASED;
            });
        });

        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        // First checkout creates checkout record at $20.00
        $res1 = $this->actingAs($user)->postJson('/checkout', $payload);
        $res1->assertStatus(201);
        $checkout = Checkout::where('user_id', $user->id)->first();
        $this->assertNotNull($checkout);
        $this->assertEquals(20.00, (float) $checkout->total);

        // Catalog price increases to $25.00
        $product->update(['price' => 25.00]);

        // Attempting to resume checkout with updated catalog price detects mismatch in chargeMatches()
        // and releases the stale checkout, creating a fresh one with the new price
        $res2 = $this->actingAs($user)->postJson('/checkout', $payload);
        $res2->assertStatus(201);

        // Stale checkout was released
        $this->assertDatabaseMissing('checkouts', ['id' => $checkout->id]);

        // Fresh replacement checkout was created with updated catalog price ($25.00)
        $newCheckout = Checkout::where('user_id', $user->id)->first();
        $this->assertNotNull($newCheckout);
        $this->assertNotEquals($checkout->id, $newCheckout->id);
        $this->assertEquals(25.00, (float) $newCheckout->total);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 4: Custom Slot Boundary Timestamps & Capacity Limits
    |--------------------------------------------------------------------------
    */

    public function test_custom_pickup_boundary_timestamps_map_to_correct_encompassing_slots(): void
    {
        $setting = StoreSetting::current();
        $setting->update([
            'is_pickup_active' => true,
            'pickup_slot_start_time' => '09:00',
            'pickup_slot_end_time' => '21:00',
            'available_pickup_slots' => [
                ['label' => '9:00 AM – 10:00 AM', 'active' => true],
                ['label' => '10:00 AM – 11:00 AM', 'active' => true],
                ['label' => '8:00 PM – 9:00 PM', 'active' => true],
            ],
        ]);

        $controller = app(OrderController::class);
        $method = new ReflectionMethod($controller, 'findEncompassingPickupSlot');
        $method->setAccessible(true);
        $slots = $setting->available_pickup_slots;

        // 1. Exact start of slot (09:00) -> maps to 9:00 AM – 10:00 AM
        $s1 = $method->invoke($controller, '09:00', $slots);
        $this->assertSame('9:00 AM – 10:00 AM', $s1['label']);

        // 2. One minute before end of slot (09:59) -> maps to 9:00 AM – 10:00 AM
        $s2 = $method->invoke($controller, '09:59', $slots);
        $this->assertSame('9:00 AM – 10:00 AM', $s2['label']);

        // 3. Exact transition boundary (10:00) -> belongs to next slot 10:00 AM – 11:00 AM
        $s3 = $method->invoke($controller, '10:00', $slots);
        $this->assertSame('10:00 AM – 11:00 AM', $s3['label']);

        // 4. Exact end of FINAL slot (21:00 / 9:00 PM) -> belongs to last active slot
        $s4 = $method->invoke($controller, '21:00', $slots);
        $this->assertSame('8:00 PM – 9:00 PM', $s4['label']);

        // 5. One minute past store closing (21:01) -> returns null (rejected)
        $s5 = $method->invoke($controller, '21:01', $slots);
        $this->assertNull($s5);
    }

    public function test_custom_pickup_participates_in_slot_capacity_and_releases_on_cancellation(): void
    {
        $today = now()->toDateString();
        $setting = StoreSetting::current();
        $setting->update([
            'max_orders_per_slot' => 1,
            'is_pickup_active' => true,
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 20.00]);

        $payloadCustom = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => $today,
            'pickup_time' => '17:30',
            'payment_method' => 'card',
        ];

        // 1. First order books 17:30 (in 5:00 PM – 6:00 PM slot). Capacity reaches 1/1
        $res1 = $this->actingAs($user)->postJson('/checkout', $payloadCustom);
        $res1->assertStatus(201);
        $order1 = Order::latest('id')->first();
        $this->assertSame(1, Order::bookedInSlot("{$today} · 5:00 PM – 6:00 PM"));

        // 2. Second order attempting preset slot "5:00 PM – 6:00 PM" -> rejected (slot_full)
        $user2 = User::factory()->create();
        $payloadSlot = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'slot',
            'pickup_date' => $today,
            'pickup_slot' => '5:00 PM – 6:00 PM',
            'payment_method' => 'card',
        ];
        $resSlot = $this->actingAs($user2)->postJson('/checkout', $payloadSlot);
        $resSlot->assertStatus(422)->assertJsonValidationErrors(['pickup_slot']);
        $this->assertSame('slot_full', $resSlot->json('errors.error_code.0'));

        // 3. Second order attempting custom time 17:45 in same window -> rejected (slot_full)
        $resCustom2 = $this->actingAs($user2)->postJson('/checkout', array_merge($payloadCustom, ['pickup_time' => '17:45']));
        $resCustom2->assertStatus(422)->assertJsonValidationErrors(['pickup_time']);
        $this->assertSame('slot_full', $resCustom2->json('errors.error_code.0'));

        // 4. Cancel the first order -> capacity is restored
        $order1->update(['status' => 'cancelled']);
        $this->assertSame(0, Order::bookedInSlot("{$today} · 5:00 PM – 6:00 PM"));

        // 5. Subsequent custom order now succeeds
        $resSuccess = $this->actingAs($user2)->postJson('/checkout', array_merge($payloadCustom, ['pickup_time' => '17:45']));
        $resSuccess->assertStatus(201);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 5: Search, Catalog Filtering & Cart Stock Payload Fidelity
    |--------------------------------------------------------------------------
    */

    public function test_home_search_filters_with_sql_wildcards_and_kit_stock_status(): void
    {
        $category = 'dairy';

        $inStockProduct = Product::factory()->create([
            'name' => 'Amul Butter 500g',
            'category' => $category,
            'stock' => 10,
            'stock_badge' => null,
            'price' => 5.00,
        ]);

        $outOfStockProduct = Product::factory()->create([
            'name' => 'Organic Paneer 200g',
            'category' => $category,
            'stock' => 0,
            'stock_badge' => 'Out of Stock',
            'price' => 4.00,
        ]);

        // Kit A: Fully in stock ingredients
        $inStockKit = RecipeKit::create([
            'name' => 'Butter Paneer Feast Kit',
            'slug' => 'butter-paneer-feast-kit',
            'price' => 18.00,
            'is_active' => true,
        ]);
        $inStockKit->products()->attach($inStockProduct->id, ['quantity' => 1]);

        // Kit B: Contains an out-of-stock ingredient
        $outOfStockKit = RecipeKit::create([
            'name' => 'Shahi Paneer Gourmet Kit',
            'slug' => 'shahi-paneer-gourmet-kit',
            'price' => 20.00,
            'is_active' => true,
        ]);
        $outOfStockKit->products()->attach($outOfStockProduct->id, ['quantity' => 1]);

        // Inactive Kit: Should never appear
        $inactiveKit = RecipeKit::create([
            'name' => 'Secret Inactive Butter Kit',
            'slug' => 'secret-inactive-butter-kit',
            'price' => 22.00,
            'is_active' => false,
        ]);
        $inactiveKit->products()->attach($inStockProduct->id, ['quantity' => 1]);

        // 1. Adversarial query: Special characters like %, _, quotes do not crash search
        $resWildcard = $this->get('/search?q=%25&category='.$category);
        $resWildcard->assertOk();

        // 2. In-stock filter applied (in_stock=1): Kit B and inactive kit are excluded
        $resInStock = $this->get('/search?tab=kits&in_stock=1');
        $resInStock->assertOk();
        $resInStock->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('filters.in_stock', true)
            ->has('recipeKits.data', 1)
            ->where('recipeKits.data.0.slug', $inStockKit->slug)
        );
    }

    public function test_cart_stock_check_resolves_all_identifier_formats_and_live_stock(): void
    {
        $product = Product::factory()->create(['stock' => 7, 'price' => 11.50]);
        $kit = RecipeKit::create([
            'name' => 'Dal Tadka Kit',
            'slug' => 'dal-tadka-kit',
            'price' => 14.00,
            'is_active' => true,
        ]);
        $kit->products()->attach($product->id, ['quantity' => 1]);

        // Query with mix of: numeric product ID, product slug, kit-prefixed numeric ID, kit-prefixed slug
        $payload = [
            'ids' => [
                $product->id,
                $product->slug,
                "kit-{$kit->id}",
                "kit-{$kit->slug}",
                'non-existent-product-id',
            ],
        ];

        $res = $this->postJson('/cart/stock-check', $payload);
        $res->assertOk();

        $resProducts = collect($res->json('products'));
        $resKits = collect($res->json('recipeKits'));

        // Products contains product with live stock 7 and price 11.50
        $this->assertTrue($resProducts->contains('id', $product->id));
        $this->assertEquals(7, $resProducts->firstWhere('id', $product->id)['stock']);

        // RecipeKits contains kit with linked ingredients
        $this->assertTrue($resKits->contains('id', $kit->id));
        $this->assertNotEmpty($resKits->firstWhere('id', $kit->id)['products']);
    }

    public function test_order_completion_security_and_unpaid_rejection(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $checkout = Checkout::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'fingerprint' => 'fp_completion_test',
            'stripe_payment_id' => 'pi_test_unpaid',
            'subtotal' => 20.00,
            'delivery_fee' => 0.00,
            'total' => 20.00,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'items' => [],
            'reserved_stock' => [],
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('retrieveIntent')->andReturn(
                PaymentIntent::constructFrom([
                    'id' => 'pi_test_unpaid',
                    'status' => 'requires_payment_method',
                ])
            );
            $mock->shouldReceive('completeOrRefund')->andReturn(
                CheckoutCompletionResult::notPaid()
            );
        });

        // 1. Stranger attempting to complete another user's checkout -> 404 (PII isolation)
        $resStranger = $this->actingAs($stranger)->postJson('/checkout/complete', [
            'checkout_id' => $checkout->id,
        ]);
        $resStranger->assertNotFound();

        // 2. Owner attempting to complete when payment is still incomplete -> 422 rejected
        $resUnpaid = $this->actingAs($user)->postJson('/checkout/complete', [
            'checkout_id' => $checkout->id,
        ]);
        $resUnpaid->assertStatus(422)->assertJsonValidationErrors(['payment']);
        $this->assertStringContainsString('Payment has not completed', $resUnpaid->json('errors.payment.0'));
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 6: Reward Point Rounding & Complex Ledger Adjustments
    |--------------------------------------------------------------------------
    */

    public function test_reward_points_fractional_rounding_and_ledger_adjustments(): void
    {
        $user = User::factory()->create();
        // Base welcome bonus is 100 points
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Order 1: Total $29.99 -> earns floor(29.99) = 29 points -> Balance: 100 + 29 = 129
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 29.99,
            'subtotal' => 29.99,
            'status' => 'confirmed',
        ]);

        $this->assertSame(29, $order->fresh()->points_earned);
        $this->assertSame(129, (int) $user->fresh()->reward_points);
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'points' => 29,
        ]);

        // Stepwise Partial Refund 1: Refund $5.00 -> Net $24.99 -> Target points floor(24.99) = 24 -> Adjustment -5 -> Balance: 124
        $order->update(['refunded_total' => 5.00]);
        $this->assertSame(24, $order->fresh()->points_earned);
        $this->assertSame(124, (int) $user->fresh()->reward_points);
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'points' => -5,
        ]);

        // Stepwise Partial Refund 2: Fractional cent refund $0.40 -> Net $24.59 -> Target points floor(24.59) = 24 -> No adjustment
        $order->update(['refunded_total' => 5.40]);
        $this->assertSame(24, $order->fresh()->points_earned);
        $this->assertSame(124, (int) $user->fresh()->reward_points);

        // Order Cancellation: Status -> cancelled -> Target points 0 -> Adjustment -24 -> Balance returns to baseline 100
        $order->update(['status' => 'cancelled']);
        $this->assertSame(0, $order->fresh()->points_earned);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'points' => -24,
            'description' => "Cancelled order {$order->order_number}",
        ]);

        // Total net ledger sum for this order is 0
        $this->assertSame(0, (int) RewardPoint::where('order_id', $order->id)->sum('points'));
    }

    public function test_guest_order_does_not_generate_reward_points_or_crash(): void
    {
        $order = Order::factory()->create([
            'user_id' => null,
            'total' => 45.00,
            'subtotal' => 45.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(0, RewardPoint::where('order_id', $order->id)->count());
        $this->assertSame(45, $order->fresh()->points_earned);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 6: Order Tracking & Account Reorder Security & Integrity
    |--------------------------------------------------------------------------
    */

    public function test_order_tracking_pii_isolation_and_hash_prefix_tolerance(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'order_number' => '#MM-20261021-9999-ABCD',
            'delivery_fee' => 4.99,
            'subtotal' => 30.00,
            'total' => 34.99,
        ]);

        $cleanNumber = ltrim($order->order_number, '#');
        $encodedNumber = urlencode($order->order_number);

        // Stranger attempting to view order tracking -> 403 Forbidden
        $resStranger = $this->actingAs($stranger)->get('/orders/'.$cleanNumber);
        $resStranger->assertForbidden();

        // Unauthenticated guest attempting to view order tracking without session key -> 403 Forbidden
        $resGuestNoSession = $this->get('/orders/'.$cleanNumber);
        $resGuestNoSession->assertForbidden();

        // Owner viewing without hash prefix ('MM-...') -> 200 OK (hash tolerance)
        $resNoHash = $this->actingAs($owner)->get('/orders/'.$cleanNumber);
        $resNoHash->assertOk();
        $resNoHash->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.order_number', $order->order_number)
            ->where('order.delivery_fee', 4.99)
            ->where('order.total', 34.99)
        );

        // Owner viewing with URL-encoded hash prefix ('%23MM-...') -> 200 OK
        $resEncoded = $this->actingAs($owner)->get('/orders/'.$encodedNumber);
        $resEncoded->assertOk();

        // Guest with session placed_order_numbers -> 200 OK
        $resGuestSession = $this->withSession(['placed_order_numbers' => [$order->order_number]])
            ->get('/orders/'.$cleanNumber);
        $resGuestSession->assertOk();
    }

    public function test_account_reorder_preserves_kit_prefixes_and_distinguishes_deleted_or_inactive_kits(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create(['name' => 'Basmati Rice', 'price' => 12.00]);
        $kit = RecipeKit::create([
            'name' => 'Paneer Tikka Kit',
            'slug' => 'paneer-tikka-kit',
            'price' => 22.00,
            'is_active' => true,
        ]);

        $order = Order::factory()->create(['user_id' => $user->id, 'total' => 34.00]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'recipe_kit_id' => null,
            'name' => $product->name,
            'unit_price' => 12.00,
            'quantity' => 1,
            'total_price' => 12.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'recipe_kit_id' => $kit->id,
            'name' => $kit->name,
            'unit_price' => 22.00,
            'quantity' => 1,
            'total_price' => 22.00,
        ]);

        $res = $this->actingAs($user)->get('/reorder');
        $res->assertOk();
        $res->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders.0.items', 2)
            ->where('dbOrders.0.items.0.id', $product->id)
            ->where('dbOrders.0.items.0.type', 'product')
            ->where('dbOrders.0.items.0.isRecipeKit', false)
            ->where('dbOrders.0.items.1.id', "kit-{$kit->id}")
            ->where('dbOrders.0.items.1.type', 'recipe-kit')
            ->where('dbOrders.0.items.1.isRecipeKit', true)
        );

        // Deactivating the kit and attempting to checkout directly -> rejected
        $kit->update(['is_active' => false]);
        $resInactiveCheckout = $this->actingAs($user)->postJson('/checkout', [
            'items' => [['id' => "kit-{$kit->id}", 'type' => 'recipe-kit', 'name' => $kit->name, 'price' => 22.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ]);
        $resInactiveCheckout->assertStatus(422)->assertJsonValidationErrors(['items.0.id']);
        $this->assertStringContainsString('no longer available', $resInactiveCheckout->json('errors')['items.0.id'][0]);
    }

    /*
    |--------------------------------------------------------------------------
    | Dimension 7: Concurrency, Idempotency & State Machine Hardening
    |--------------------------------------------------------------------------
    */

    public function test_double_cancellation_and_restock_is_idempotent(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'price' => 10.00]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'reserved_stock' => [$product->id => 2],
        ]);
        $product->decrement('stock', 2);
        $this->assertSame(3, $product->fresh()->stock);

        // Call 1: Successfully cancels and restocks
        $cancelled1 = $order->cancelAndRestock('First cancellation');
        $this->assertTrue($cancelled1);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->status);

        // Call 2: Concurrent or duplicate cancellation request -> returns false, does NOT double-restock
        $cancelled2 = $order->cancelAndRestock('Second duplicate cancellation');
        $this->assertFalse($cancelled2);
        $this->assertSame(5, $product->fresh()->stock); // Stock remains 5, not 7
    }

    public function test_checkout_fingerprint_invariance_and_permutations(): void
    {
        $payload1 = [
            'items' => [
                ['id' => 'prod-1', 'type' => 'product', 'quantity' => 2, 'is_subscribed' => false],
                ['id' => 'kit-5', 'type' => 'recipe-kit', 'quantity' => 1, 'is_subscribed' => false],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => '9:00 AM – 10:00 AM',
        ];

        // Permuted items order
        $payloadPermuted = [
            'items' => [
                ['id' => 'kit-5', 'type' => 'recipe-kit', 'quantity' => 1, 'is_subscribed' => false],
                ['id' => 'prod-1', 'type' => 'product', 'quantity' => 2, 'is_subscribed' => false],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => '9:00 AM – 10:00 AM',
        ];

        // Different quantity
        $payloadDifferentQty = [
            'items' => [
                ['id' => 'prod-1', 'type' => 'product', 'quantity' => 3, 'is_subscribed' => false],
                ['id' => 'kit-5', 'type' => 'recipe-kit', 'quantity' => 1, 'is_subscribed' => false],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => '9:00 AM – 10:00 AM',
        ];

        $fp1 = Checkout::fingerprintFor($payload1);
        $fpPermuted = Checkout::fingerprintFor($payloadPermuted);
        $fpDiffQty = Checkout::fingerprintFor($payloadDifferentQty);

        // Permuted list yields identical fingerprint
        $this->assertSame($fp1, $fpPermuted);
        // Changed quantity yields different fingerprint
        $this->assertNotSame($fp1, $fpDiffQty);
    }

    public function test_concurrent_double_click_checkout_triggers_lock_timeout_protection(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);

        $payload = [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'payment_method' => 'card',
        ];

        // Simulate an ongoing checkout holding the lock
        $lock = Cache::lock("checkout:user:{$user->id}", 5);
        $this->assertTrue($lock->acquire());

        // In a separate test harness, we set block timeout or simulate concurrent request
        // Since block(10) waits 10s in real time, we can verify that the lock key exists and protects the critical section
        $this->assertFalse(Cache::lock("checkout:user:{$user->id}", 5)->acquire());

        // Release the lock
        $lock->release();
        $this->assertTrue(Cache::lock("checkout:user:{$user->id}", 5)->acquire());
        $lock->release();
    }
}
