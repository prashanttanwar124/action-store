<?php

namespace Tests\Feature;

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
}
