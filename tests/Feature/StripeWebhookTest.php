<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StripePayments;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use Stripe\Refund;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_ignores_already_processed_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'confirmed',
            'stripe_payment_id' => 'pi_webhook_existing_123',
            'total' => 25.00,
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_existing_123',
                    'status' => 'succeeded',
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

    public function test_webhook_turns_paid_checkout_into_order(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_paid_checkout_456']);

        $response = $this->postJson('/stripe/webhook', $this->succeededPayload($checkout, 2000));

        $response->assertStatus(200)
            ->assertJson(['received' => true]);

        $order = Order::where('stripe_payment_id', 'pi_paid_checkout_456')->sole();
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals($checkout->user_id, $order->user_id);
        $this->assertModelMissing($checkout);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_webhook_refunds_instead_of_creating_order_when_paid_amount_differs(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_short_payment_321']);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('refundPayment')->once()->with('pi_short_payment_321')->andReturn(Refund::constructFrom(['id' => 're_short']));
        });

        $response = $this->postJson('/stripe/webhook', $this->succeededPayload($checkout, 50));

        $response->assertStatus(200);
        $this->assertEquals(0, Order::count());
        $this->assertModelMissing($checkout);
        $this->assertEquals(10, $product->fresh()->stock);
    }

    public function test_webhook_refunds_payment_whose_checkout_holds_a_different_payment(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_current']);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('refundPayment')->once()->with('pi_stale')->andReturn(Refund::constructFrom(['id' => 're_stale']));
        });

        $payload = $this->succeededPayload($checkout, 2000);
        $payload['data']['object']['id'] = 'pi_stale';

        $this->postJson('/stripe/webhook', $payload)->assertOk();

        $this->assertEquals(0, Order::count());
        $this->assertModelExists($checkout);
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

    public function test_webhook_payment_failed_keeps_checkout_so_customer_can_retry(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_declined_789']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_declined_789', 'metadata' => ['checkout_id' => $checkout->id]]],
        ]);

        $response->assertStatus(200);
        $this->assertModelExists($checkout);
        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_webhook_payment_canceled_releases_checkout_without_altering_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_canceled_790']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'payment_intent.canceled',
            'data' => ['object' => ['id' => 'pi_canceled_790', 'metadata' => ['checkout_id' => $checkout->id]]],
        ]);

        $response->assertStatus(200);
        $this->assertModelMissing($checkout);
        $this->assertEquals(5, $product->fresh()->stock);
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
                    'refunded' => true,
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_webhook_partial_refund_keeps_order_and_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create([
            'stripe_payment_id' => 'pi_partial_refund_555',
            'status' => 'confirmed',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 15,
            'quantity' => 2,
            'total_price' => 30,
        ]);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_partial_123',
                    'payment_intent' => 'pi_partial_refund_555',
                    'refunded' => false,
                    'amount_refunded' => 500,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('confirmed', $order->fresh()->status);
        $this->assertEquals(3, $product->fresh()->stock);
        $this->assertStringContainsString('PARTIAL REFUND ON STRIPE: $5.00', $order->fresh()->notes);
    }

    public function test_release_expired_checkouts_command_deletes_checkout_without_altering_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3, 'price' => 10.00]);
        $expired = Checkout::factory()->holding($product, 2)->expired()->create();
        $active = Checkout::factory()->holding($product, 1)->create();

        $this->artisan('checkouts:release-expired')
            ->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($active);
        $this->assertEquals(3, $product->fresh()->stock);
    }

    public function test_webhook_auto_refunds_when_stock_insufficient(): void
    {
        $product = Product::factory()->create(['stock' => 0, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_stock_out_webhook']);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('currency')->andReturn('cad');
            $mock->shouldReceive('amountInCents')->andReturn(2000);
            $mock->shouldReceive('refundPayment')->once()->with('pi_stock_out_webhook')->andReturn(Refund::constructFrom(['id' => 're_webhook_oos']));
        });

        $response = $this->postJson('/stripe/webhook', $this->succeededPayload($checkout, 2000));

        $response->assertStatus(200);
        $this->assertEquals(0, Order::count());
        $this->assertModelMissing($checkout);
    }

    public function test_webhook_returns_500_and_retains_checkout_when_refund_fails(): void
    {
        $product = Product::factory()->create(['stock' => 0, 'price' => 10.00]);
        $checkout = Checkout::factory()->holding($product, 2)->create(['stripe_payment_id' => 'pi_fail_refund_webhook']);

        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('currency')->andReturn('cad');
            $mock->shouldReceive('amountInCents')->andReturn(2000);
            $mock->shouldReceive('refundPayment')->once()->with('pi_fail_refund_webhook')->andThrow(new \RuntimeException('Stripe temporary failure'));
        });

        $response = $this->postJson('/stripe/webhook', $this->succeededPayload($checkout, 2000));

        $response->assertStatus(500);
        $this->assertEquals(0, Order::count());
        $this->assertModelExists($checkout);
    }

    public function test_webhook_does_not_refund_when_order_already_created_for_deleted_checkout(): void
    {
        $order = Order::factory()->create([
            'status' => 'confirmed',
            'stripe_payment_id' => 'pi_concurrent_confirmed_123',
            'total' => 20.00,
        ]);

        // Mock StripePayments so refundPayment is NEVER called
        $this->partialMock(StripePayments::class, function (MockInterface $mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('refundPayment')->never();
        });

        // The checkout is missing (already converted and deleted), but metadata has checkout_id
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_concurrent_confirmed_123',
                    'status' => 'succeeded',
                    'amount_received' => 2000,
                    'currency' => 'cad',
                    'metadata' => ['checkout_id' => '999999'],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $payload);

        $response->assertStatus(200)->assertJson(['received' => true]);
        $this->assertEquals(1, Order::where('stripe_payment_id', 'pi_concurrent_confirmed_123')->count());
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function succeededPayload(Checkout $checkout, int $amountReceived): array
    {
        return [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $checkout->stripe_payment_id,
                    'status' => 'succeeded',
                    'amount_received' => $amountReceived,
                    'currency' => 'cad',
                    'metadata' => ['checkout_id' => $checkout->id],
                ],
            ],
        ];
    }
}
