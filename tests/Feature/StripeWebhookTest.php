<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_ignores_already_processed_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'confirmed',
            'stripe_payment_id' => 'pi_webhook_existing_123',
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_existing_123',
                    'amount_received' => 2500,
                    'currency' => 'cad',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);

        $this->assertEquals(1, Order::where('stripe_payment_id', 'pi_webhook_existing_123')->count());
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_webhook_confirms_pending_order_on_payment_intent_succeeded(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending_payment',
            'stripe_payment_id' => 'pi_pending_webhook_456',
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_pending_webhook_456',
                    'amount_received' => 2000,
                    'currency' => 'cad',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);

        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_webhook_rejects_missing_signature_when_webhook_secret_is_configured(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test_secret_key');

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_fake_unsigned']],
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Missing Stripe-Signature header']);
    }

    public function test_orders_table_enforces_unique_stripe_payment_id(): void
    {
        Order::factory()->create([
            'stripe_payment_id' => 'pi_unique_constraint_test',
        ]);

        $this->expectException(QueryException::class);

        Order::factory()->create([
            'stripe_payment_id' => 'pi_unique_constraint_test',
        ]);
    }

    public function test_checkout_rejects_arbitrary_unwhitelisted_payment_methods(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 10]);

        $response = $this->actingAs($user)->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10, 'quantity' => 1]],
            'payment_method' => 'unauthorized_bypass_method',
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_browser_checkout_succeeds_when_order_already_created_for_payment_intent(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 20]);

        // 1. Order already created
        $order = Order::factory()->create([
            'order_number' => 'MM-20261004-9999',
            'stripe_payment_id' => 'pi_webhook_first_555',
            'total' => 20.00,
            'status' => 'confirmed',
        ]);

        // 2. Browser subsequently posts /checkout with the same stripe_payment_id
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20, 'quantity' => 1]],
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_webhook_first_555',
            'fulfillment_type' => 'Store Pickup',
        ]);

        // 3. Must return 200 with the existing order
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => 'MM-20261004-9999',
                    'stripe_payment_id' => 'pi_webhook_first_555',
                ],
            ]);
    }

    public function test_pre_payment_validate_endpoint_checks_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2, 'price' => 15.00]);

        // 1. Requesting quantity > stock should fail validation with 422
        $response = $this->actingAs($user)->postJson('/checkout/validate', [
            'items' => [['id' => $product->id, 'price' => 15.00, 'quantity' => 5]],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // 2. Requesting valid stock passes
        $validResponse = $this->actingAs($user)->postJson('/checkout/validate', [
            'items' => [['id' => $product->id, 'price' => 15.00, 'quantity' => 2]],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $validResponse->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'subtotal' => 30.00,
            ]);
    }

    public function test_webhook_payment_failed_cancels_pending_order_and_restocks(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = Order::factory()->create([
            'status' => 'pending_payment',
            'stripe_payment_id' => 'pi_failed_payment_789',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 10,
            'quantity' => 2,
            'total_price' => 20,
        ]);

        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_payment_789',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(7, $product->fresh()->stock);
    }

    public function test_webhook_charge_refunded_restocks_inventory(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create([
            'stripe_payment_id' => 'pi_refund_test_444',
            'status' => 'confirmed',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 15,
            'quantity' => 2,
            'total_price' => 30,
        ]);

        $payload = [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_refund_123',
                    'payment_intent' => 'pi_refund_test_444',
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_cancel_expired_pending_orders_command_cancels_stale_orders(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $staleOrder = Order::factory()->create([
            'status' => 'pending_payment',
            'created_at' => now()->subMinutes(20),
        ]);
        $staleOrder->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 10,
            'quantity' => 2,
            'total_price' => 20,
        ]);

        $this->artisan('orders:cancel-expired-pending')
            ->assertSuccessful();

        $this->assertEquals('cancelled', $staleOrder->fresh()->status);
        $this->assertEquals(5, $product->fresh()->stock);
    }
}
