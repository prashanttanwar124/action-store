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

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
        $this->user = User::factory()->create([
            'name' => 'Aarav Patel',
            'email' => 'aarav@example.com',
        ]);
    }

    public function test_unauthenticated_user_is_redirected_from_checkout_page(): void
    {
        $response = $this->get('/checkout');

        $response->assertRedirect('/login');
    }

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $product = Product::factory()->create(['price' => 10.00]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1]],
        ]);

        $response->assertUnauthorized();
    }

    public function test_checkout_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/checkout');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Checkout'));
    }

    public function test_authenticated_user_places_order_linked_to_account(): void
    {
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
            'pickup_timing_mode' => 'asap',
        ];

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'customer_name' => 'Aarav Patel',
            'customer_email' => 'aarav@example.com',
            'subtotal' => 14.97,
            'total' => 14.97,
            'points_earned' => 14,
        ]);

        // Verify orders appear in user's account page
        $accountResponse = $this->actingAs($this->user)->get('/account');
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
        $response = $this->actingAs($this->user)->postJson('/checkout', [
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
        ];

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

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

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

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

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
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

        $response = $this->actingAs($this->user)->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
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

        $response = $this->actingAs($this->user)->postJson('/checkout', [
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

        $response = $this->actingAs($this->user)->postJson('/checkout', [
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

        $response = $this->actingAs($this->user)->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 2, 'is_subscribed' => false],
                ['id' => $product->id, 'name' => $product->name, 'price' => 9.50, 'quantity' => 2, 'is_subscribed' => true],
            ],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(6, $product->fresh()->stock);
    }

    public function test_checkout_ignores_client_supplied_stripe_payment_id(): void
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 25.00,
        ]);

        $response = $this->actingAs($this->user)->postJson('/checkout', [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 25.00,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'stripe',
            'stripe_payment_id' => 'pi_forged_by_client',
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'customer_email' => 'aarav@example.com',
            'payment_method' => 'stripe',
            'stripe_payment_id' => null,
        ]);
    }

    public function test_authenticated_checkout_records_phone(): void
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 15.00,
        ]);

        $response = $this->actingAs($this->user)->postJson('/checkout', [
            'customer_phone' => '416-555-0199',
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 15.00,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'customer_name' => 'Aarav Patel',
            'customer_email' => 'aarav@example.com',
            'customer_phone' => '416-555-0199',
        ]);
    }
}
