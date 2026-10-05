<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_page_can_be_rendered(): void
    {
        $response = $this->get('/checkout');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Checkout'));
    }

    public function test_guest_can_place_an_order(): void
    {
        $product = Product::factory()->create([
            'name' => 'Aashirvaad Shudh Chakki Atta',
            'price' => 12.99,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 12.99,
                    'quantity' => 2,
                    'size' => '10 lb',
                    'image' => '/images/products/atta.jpg',
                    'is_subscribed' => false,
                ],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Today 4–5 pm',
            'pickup_location' => 'Milpitas Hub (1200 S Main St)',
            'customer_name' => 'Guest Shopper',
            'customer_email' => 'shopper@example.com',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Guest Shopper',
            'customer_email' => 'shopper@example.com',
            'subtotal' => 25.98,
            'total' => 25.98,
            'points_earned' => 25,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'name' => 'Aashirvaad Shudh Chakki Atta',
            'quantity' => 2,
            'unit_price' => 12.99,
            'total_price' => 25.98,
        ]);
    }

    public function test_authenticated_user_places_order_linked_to_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Aarav Patel',
            'email' => 'aarav@example.com',
        ]);

        $product = Product::factory()->create([
            'name' => 'Paneer Fresh Block',
            'price' => 4.99,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 4.99,
                    'quantity' => 3,
                    'size' => '400 g',
                    'image' => '/images/products/paneer.jpg',
                ],
            ],
            'payment_method' => 'apple-pay',
            'pickup_slot' => 'Today 6–7 pm',
        ];

        $response = $this->actingAs($user)->postJson('/checkout', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'customer_name' => 'Aarav Patel',
            'customer_email' => 'aarav@example.com',
            'subtotal' => 14.97,
            'total' => 14.97,
            'points_earned' => 14,
        ]);

        // Verify orders appear in user's account page
        $accountResponse = $this->actingAs($user)->get('/account');
        $accountResponse->assertOk();
        $accountResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders', 1)
            ->where('dbOrders.0.total', 14.97)
            ->where('dbOrders.0.itemCount', 3)
        );
    }

    public function test_checkout_requires_items(): void
    {
        $response = $this->postJson('/checkout', [
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_product_stock_decrements_when_order_is_placed(): void
    {
        $product = Product::factory()->create([
            'name' => 'Fresh Malai Paneer',
            'stock' => 24,
            'stock_badge' => 'In stock · 24 left',
            'price' => 5.99,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 5.99,
                    'quantity' => 2,
                    'size' => '400 g',
                    'image' => '/images/products/paneer.jpg',
                ],
            ],
            'payment_method' => 'card',
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.com',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);

        $product->refresh();
        $this->assertEquals(22, $product->stock);
        $this->assertEquals('In stock · 22 left', $product->stock_badge);
    }

    public function test_product_stock_badge_becomes_out_of_stock_when_exhausted(): void
    {
        $product = Product::factory()->create([
            'name' => 'Organic Paneer',
            'stock' => 3,
            'stock_badge' => 'In stock · 3 left',
            'price' => 5.99,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 5.99,
                    'quantity' => 3,
                ],
            ],
            'payment_method' => 'card',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);

        $product->refresh();
        $this->assertEquals(0, $product->stock);
        $this->assertEquals('Out of stock', $product->stock_badge);
    }

    public function test_home_delivery_applies_fee_when_subtotal_is_below_threshold(): void
    {
        StoreSetting::current()->update([
            'is_delivery_active' => true,
            'delivery_days' => [strtolower(now()->format('l'))],
        ]);

        $product = Product::factory()->create([
            'name' => 'Basmati Rice',
            'price' => 15.00,
            'stock' => 10,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 15.00,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '456 Main St, Apt 2B',
            'delivery_city' => 'Edison',
            'delivery_zip' => '08820',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'fulfillment_type' => 'Home Delivery',
            'subtotal' => 15.00,
            'delivery_fee' => 4.99,
            'total' => 19.99,
        ]);
    }

    public function test_home_delivery_is_free_when_subtotal_meets_threshold(): void
    {
        StoreSetting::current()->update([
            'is_delivery_active' => true,
            'delivery_days' => [strtolower(now()->format('l'))],
        ]);

        $product = Product::factory()->create([
            'name' => 'Saffron Box',
            'price' => 60.00,
            'stock' => 10,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 60.00,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '456 Main St, Apt 2B',
            'delivery_city' => 'Edison',
            'delivery_zip' => '08820',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'fulfillment_type' => 'Home Delivery',
            'subtotal' => 60.00,
            'delivery_fee' => 0.00,
            'total' => 60.00,
        ]);
    }

    public function test_home_delivery_is_rejected_when_delivery_is_disabled(): void
    {
        StoreSetting::current()->update(['is_delivery_active' => false]);

        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1],
            ],
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '123 Fake Street',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fulfillment_type']);
    }

    public function test_insufficient_stock_rejects_order(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        $response = $this->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 10],
            ],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
        $this->assertEquals(2, $product->fresh()->stock);
    }

    public function test_multiple_rows_of_same_product_aggregate_stock_correctly(): void
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 10.00,
            'has_subscription' => true,
        ]);

        $response = $this->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 2, 'is_subscribed' => false],
                ['id' => $product->id, 'name' => $product->name, 'price' => 9.50, 'quantity' => 2, 'is_subscribed' => true],
            ],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(6, $product->fresh()->stock);
    }

    public function test_checkout_supports_stripe_payment_method_and_stores_stripe_payment_id(): void
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 25.00,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 25.00,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'stripe',
            'stripe_payment_id' => 'pi_test_1234567890',
            'fulfillment_type' => 'Store Pickup',
            'customer_name' => 'Stripe Shopper',
            'customer_email' => 'stripe@example.com',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'customer_email' => 'stripe@example.com',
            'payment_method' => 'stripe',
            'stripe_payment_id' => 'pi_test_1234567890',
        ]);
    }

    public function test_create_payment_intent_validates_amount(): void
    {
        $response = $this->postJson('/checkout/create-payment-intent', [
            'amount' => 0.10, // less than minimum 0.50
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_intent_returns_503_when_stripe_is_not_configured(): void
    {
        config(['services.stripe.secret' => null]);

        $response = $this->postJson('/checkout/create-payment-intent', [
            'amount' => 25.00,
            'currency' => 'cad',
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'configured' => false,
            ]);
    }
}
