<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
