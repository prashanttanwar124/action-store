<?php

namespace Tests\Feature;

use App\Models\Product;
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
}
