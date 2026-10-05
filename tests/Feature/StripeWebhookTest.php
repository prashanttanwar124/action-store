<?php

namespace Tests\Feature;

use App\Jobs\RecoverStripeOrderJob;
use App\Models\Order;
use App\Models\Product;
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
            'stripe_payment_id' => 'pi_webhook_existing_123',
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_existing_123',
                    'amount_received' => 2500,
                    'currency' => 'cad',
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);

        $this->assertEquals(1, Order::where('stripe_payment_id', 'pi_webhook_existing_123')->count());
    }

    public function test_webhook_recovers_missing_order_from_payment_intent_metadata(): void
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 20.00,
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_recovered_payment_456',
                    'amount_received' => 2000,
                    'currency' => 'cad',
                    'metadata' => [
                        'customer_name' => 'Recovered Customer',
                        'customer_email' => 'recovered@example.com',
                        'fulfillment_type' => 'Store Pickup',
                        'items_json' => json_encode([
                            [
                                'id' => $product->id,
                                'price' => 20.00,
                                'quantity' => 1,
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);

        $this->assertDatabaseHas('orders', [
            'stripe_payment_id' => 'pi_recovered_payment_456',
            'customer_email' => 'recovered@example.com',
            'status' => 'confirmed',
        ]);

        $this->assertEquals(9, $product->fresh()->stock);
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

    public function test_webhook_returns_500_on_recovery_stock_or_cents_mismatch(): void
    {
        $product = Product::factory()->create([
            'stock' => 1,
            'price' => 50.00,
        ]);

        // Payload with 5 units when only 1 is in stock
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_insufficient_stock_789',
                    'amount_received' => 25000,
                    'currency' => 'cad',
                    'metadata' => [
                        'items_json' => json_encode([
                            [
                                'id' => $product->id,
                                'price' => 50.00,
                                'quantity' => 5,
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(500)
            ->assertJsonStructure(['error']);

        $this->assertDatabaseMissing('orders', [
            'stripe_payment_id' => 'pi_insufficient_stock_789',
        ]);
        $this->assertEquals(1, $product->fresh()->stock);
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
        $product = Product::factory()->create(['stock' => 10, 'price' => 10]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10, 'quantity' => 1]],
            'payment_method' => 'unauthorized_bypass_method',
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_create_payment_intent_rejects_intent_id_not_owned_by_active_session(): void
    {
        Config::set('services.stripe.secret', 'sk_test_mock_secret');

        $response = $this->postJson('/checkout/create-payment-intent', [
            'amount' => 25.00,
            'payment_intent_id' => 'pi_stolen_intent_123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Payment intent does not belong to your active checkout session.']);
    }

    public function test_webhook_recovers_order_with_delivery_address_and_pickup_slot(): void
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 30.00,
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_recovered_with_address_789',
                    'amount_received' => 3499,
                    'currency' => 'cad',
                    'metadata' => [
                        'customer_name' => 'John Doe',
                        'customer_email' => 'john@example.com',
                        'customer_phone' => '123-456-7890',
                        'fulfillment_type' => 'Home Delivery',
                        'delivery_address' => '456 Queen St, Apt 10B, Toronto',
                        'pickup_slot' => 'Tomorrow (2:00 PM - 2:30 PM)',
                        'items_json' => json_encode([
                            [
                                'id' => $product->id,
                                'price' => 30.00,
                                'quantity' => 1,
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'stripe_payment_id' => 'pi_recovered_with_address_789',
            'customer_email' => 'john@example.com',
            'delivery_address' => '456 Queen St, Apt 10B, Toronto',
            'pickup_slot' => 'Tomorrow (2:00 PM - 2:30 PM)',
            'fulfillment_type' => 'Home Delivery',
        ]);
    }

    public function test_browser_checkout_succeeds_when_webhook_already_created_order(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 20]);

        // 1. Webhook arrives first and creates the order
        $order = Order::factory()->create([
            'order_number' => 'MM-20261004-9999',
            'stripe_payment_id' => 'pi_webhook_first_555',
            'total' => 20.00,
            'status' => 'confirmed',
        ]);

        // 2. Browser subsequently posts /checkout with the same stripe_payment_id
        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 20, 'quantity' => 1]],
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_webhook_first_555',
            'fulfillment_type' => 'Store Pickup',
        ]);

        // 3. Must not throw "already used" validation error; must return 200 with the confirmed order
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order' => [
                    'order_number' => 'MM-20261004-9999',
                    'stripe_payment_id' => 'pi_webhook_first_555',
                ],
            ]);
    }

    public function test_pre_payment_validate_endpoint_checks_stock_before_charging(): void
    {
        $product = Product::factory()->create(['stock' => 2, 'price' => 15.00]);

        // 1. Requesting quantity > stock should fail validation with 422 BEFORE any card charge
        $response = $this->postJson('/checkout/validate', [
            'items' => [['id' => $product->id, 'price' => 15.00, 'quantity' => 5]],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // 2. Requesting valid stock passes
        $validResponse = $this->postJson('/checkout/validate', [
            'items' => [['id' => $product->id, 'price' => 15.00, 'quantity' => 2]],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $validResponse->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'subtotal' => 30.00,
            ]);
    }

    public function test_delayed_recovery_job_executes_recovery_from_metadata(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);

        $job = new RecoverStripeOrderJob([
            'id' => 'pi_delayed_job_recovery_111',
            'amount_received' => 2500,
            'currency' => 'cad',
            'metadata' => [
                'customer_name' => 'Delayed Customer',
                'customer_email' => 'delayed@example.com',
                'fulfillment_type' => 'Store Pickup',
                'pickup_slot' => 'Tomorrow (11:00 AM - 11:30 AM)',
                'items_json' => json_encode([
                    ['id' => $product->id, 'price' => 25.00, 'quantity' => 1],
                ]),
            ],
        ]);

        $job->handle();

        $this->assertDatabaseHas('orders', [
            'stripe_payment_id' => 'pi_delayed_job_recovery_111',
            'customer_email' => 'delayed@example.com',
            'pickup_slot' => 'Tomorrow (11:00 AM - 11:30 AM)',
        ]);
        $this->assertEquals(9, $product->fresh()->stock);
    }

    public function test_delayed_recovery_job_skips_if_order_already_created_by_browser(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 25.00]);

        // Order already placed by customer
        Order::factory()->create([
            'stripe_payment_id' => 'pi_already_placed_222',
            'total' => 25.00,
        ]);

        $job = new RecoverStripeOrderJob([
            'id' => 'pi_already_placed_222',
            'amount_received' => 2500,
            'currency' => 'cad',
            'metadata' => [
                'items_json' => json_encode([
                    ['id' => $product->id, 'price' => 25.00, 'quantity' => 1],
                ]),
            ],
        ]);

        $job->handle();

        // Count should remain 1 and stock should remain 10 (not double decremented)
        $this->assertEquals(1, Order::where('stripe_payment_id', 'pi_already_placed_222')->count());
        $this->assertEquals(10, $product->fresh()->stock);
    }
}
