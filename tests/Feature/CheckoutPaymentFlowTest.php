<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Models\Admin;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StripePayments;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Tests\TestCase;

class CheckoutPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([OrderPlaced::class]);

        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['stock' => 10, 'price' => 10.00]);
    }

    public function test_checkout_checks_stock_and_returns_client_secret_without_decrementing_stock_or_creating_order(): void
    {
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('createIntentFor')->once()->andReturn($this->newIntent('pi_new_123'));
        });

        $response = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload());

        $response->assertStatus(201)->assertJson([
            'requires_payment' => true,
            'clientSecret' => 'pi_new_123_secret',
        ]);
        $this->assertDatabaseHas('checkouts', [
            'id' => $response->json('checkout_id'),
            'stripe_payment_id' => 'pi_new_123',
            'total' => 20.00,
        ]);
        $this->assertEquals(0, Order::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_retry_after_declined_card_resumes_the_same_checkout(): void
    {
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('createIntentFor')->once()->andReturn($this->newIntent('pi_retry'));
            $mock->shouldReceive('retrieveIntent')->once()->with('pi_retry')->andReturn(
                PaymentIntent::constructFrom(['id' => 'pi_retry', 'status' => 'requires_payment_method', 'client_secret' => 'pi_retry_secret'])
            );
        });

        $first = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload());
        $retry = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload());

        $retry->assertOk()->assertJson([
            'checkout_id' => $first->json('checkout_id'),
            'clientSecret' => 'pi_retry_secret',
        ]);
        $this->assertEquals(1, Checkout::count());
        $this->assertEquals(0, Order::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_changed_cart_releases_the_old_checkout_and_starts_a_new_one(): void
    {
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('createIntentFor')->twice()->andReturn($this->newIntent('pi_old'), $this->newIntent('pi_new'));
            $mock->shouldReceive('cancelIntent')->once()->with('pi_old')->andReturn(
                PaymentIntent::constructFrom(['id' => 'pi_old', 'status' => 'canceled'])
            );
        });

        $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload(quantity: 2))->assertCreated();
        $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload(quantity: 3))->assertCreated();

        $this->assertEquals(['pi_new'], Checkout::pluck('stripe_payment_id')->all());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_complete_creates_the_order_only_after_stripe_verifies_the_payment(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->once()->andReturn($this->paidIntent($checkout));
        });

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id]);

        $response->assertOk()->assertJson(['requires_payment' => false]);
        $order = Order::sole();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame($checkout->stripe_payment_id, $order->stripe_payment_id);
        $this->assertEquals(20.00, (float) $order->total);
        $this->assertEquals(1, $order->items()->count());
        $this->assertModelMissing($checkout);
        $this->assertEquals(8, $this->product->fresh()->stock);
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    public function test_complete_rejects_a_checkout_that_is_not_paid(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->andReturn(
                $this->paidIntent($checkout, ['status' => 'requires_payment_method', 'amount_received' => 0])
            );
        });

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payment']);
        $this->assertEquals(0, Order::count());
        $this->assertModelExists($checkout);
    }

    public function test_complete_rejects_a_payment_for_a_different_amount(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->andReturn($this->paidIntent($checkout, ['amount_received' => 50]));
        });

        $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id])->assertStatus(422);

        $this->assertEquals(0, Order::count());
    }

    public function test_complete_without_stripe_cannot_create_an_order(): void
    {
        $checkout = $this->checkoutFor($this->user);

        $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id])->assertStatus(422);

        $this->assertEquals(0, Order::count());
    }

    public function test_complete_returns_the_order_when_the_webhook_created_it_first(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'stripe_payment_id' => 'pi_webhook_won']);

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', [
            'checkout_id' => 999,
            'payment_intent_id' => 'pi_webhook_won',
        ]);

        $response->assertOk()->assertJson(['order_id' => $order->id]);
    }

    public function test_customer_cannot_complete_another_customers_checkout(): void
    {
        $checkout = $this->checkoutFor(User::factory()->create());

        $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id])->assertNotFound();

        $this->assertModelExists($checkout);
    }

    public function test_paid_checkout_becomes_an_order_when_the_customer_clicks_pay_again(): void
    {
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('createIntentFor')->once()->andReturn($this->newIntent('pi_lost_confirmation'));
            $mock->shouldReceive('retrieveIntent')->andReturnUsing(fn () => $this->paidIntent(Checkout::sole()));
        });

        $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload())->assertCreated();
        $response = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload());

        $response->assertOk()->assertJson(['requires_payment' => false]);
        $this->assertSame('pi_lost_confirmation', Order::sole()->stripe_payment_id);
        $this->assertEquals(0, Checkout::count());
    }

    public function test_converting_a_checkout_twice_creates_one_order(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $staleCopy = Checkout::find($checkout->id);

        $first = $checkout->convertToOrder();
        $second = $staleCopy->convertToOrder();

        $this->assertEquals(1, Order::count());
        $this->assertTrue($first->is($second));
    }

    public function test_expired_checkout_is_cancelled_on_stripe_then_released(): void
    {
        $checkout = $this->checkoutFor($this->user, expired: true);
        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('cancelIntent')->once()->with($checkout->stripe_payment_id)->andReturn(
                PaymentIntent::constructFrom(['id' => $checkout->stripe_payment_id, 'status' => 'canceled'])
            );
        });

        $this->artisan('checkouts:release-expired')->assertSuccessful();

        $this->assertModelMissing($checkout);
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_expired_checkout_that_was_paid_becomes_an_order(): void
    {
        $checkout = $this->checkoutFor($this->user, expired: true);
        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('cancelIntent')->andThrow(new \RuntimeException('This PaymentIntent has already succeeded.'));
            $mock->shouldReceive('retrieveIntent')->andReturn($this->paidIntent($checkout));
        });

        $this->artisan('checkouts:release-expired')->assertSuccessful();

        $this->assertSame($checkout->stripe_payment_id, Order::sole()->stripe_payment_id);
        $this->assertModelMissing($checkout);
        $this->assertEquals(8, $this->product->fresh()->stock);
    }

    public function test_expired_checkout_with_a_processing_payment_is_kept(): void
    {
        $checkout = $this->checkoutFor($this->user, expired: true);
        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('cancelIntent')->andThrow(new \RuntimeException('This PaymentIntent is processing.'));
            $mock->shouldReceive('retrieveIntent')->andReturn(
                $this->paidIntent($checkout, ['status' => 'processing', 'amount_received' => 0])
            );
        });

        $this->artisan('checkouts:release-expired')->assertSuccessful();

        $this->assertModelExists($checkout);
        $this->assertEquals(0, Order::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_payment_for_a_released_checkout_is_refunded(): void
    {
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('refundPayment')->once()->with('pi_orphan')->andReturn(Refund::constructFrom(['id' => 're_123']));
        });

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_orphan',
                'status' => 'succeeded',
                'amount_received' => 2000,
                'currency' => 'cad',
                'metadata' => ['checkout_id' => '424242'],
            ]],
        ]);

        $response->assertOk();
        $this->assertEquals(0, Order::count());
    }

    public function test_complete_auto_refunds_if_item_goes_out_of_stock(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $this->product->update(['stock' => 0]);

        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->once()->andReturn($this->paidIntent($checkout));
            $mock->shouldReceive('refundPayment')->once()->with($checkout->stripe_payment_id)->andReturn(Refund::constructFrom(['id' => 're_out_of_stock']));
        });

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment']);
        $this->assertEquals('out_of_stock_refunded', $response->json('errors.error_code.0'));
        $this->assertEquals(0, Order::count());
        $this->assertModelMissing($checkout);
    }

    public function test_slot_held_by_placed_order_counts_towards_capacity(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        StoreSetting::current()->update([
            'max_orders_per_slot' => 1,
            'pickup_slot_start_time' => '09:00',
            'pickup_slot_end_time' => '21:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        $slotLabel = '10:00 AM – 11:00 AM';
        $today = now()->toDateString();
        Order::factory()->create(['pickup_slot' => "{$today} · {$slotLabel}", 'status' => 'confirmed']);

        $response = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload() + [
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'slot',
            'pickup_date' => $today,
            'pickup_slot' => $slotLabel,
        ]);

        $response->assertUnprocessable();
        $this->assertEquals('slot_full', $response->json('errors.error_code.0'));
    }

    public function test_checkout_is_refused_in_production_when_stripe_is_not_configured(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload());

        $response->assertStatus(422)->assertJsonValidationErrors(['payment']);
        $this->assertEquals(0, Checkout::count());
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    public function test_cancelling_an_order_returns_its_recipe_kit_ingredients(): void
    {
        $order = Order::factory()->create(['reserved_stock' => [$this->product->id => 3]]);
        $order->items()->create([
            'product_id' => null,
            'name' => 'Paneer Curry Kit',
            'unit_price' => 30,
            'quantity' => 1,
            'total_price' => 30,
        ]);

        $this->assertTrue($order->cancelAndRestock('test'));
        $this->assertFalse($order->cancelAndRestock('again'));

        $this->assertEquals(13, $this->product->fresh()->stock);
    }

    public function test_admin_cannot_reopen_a_cancelled_order(): void
    {
        $order = Order::factory()->create(['status' => 'cancelled']);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson("/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertStatus(422);

        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    public function test_admin_cancel_refunds_a_paid_order_and_returns_its_stock(): void
    {
        $order = $this->paidOrder();
        $this->fakeStripe(function (MockInterface $mock) use ($order) {
            $mock->shouldReceive('refundPayment')->once()->with($order->stripe_payment_id)->andReturn(Refund::constructFrom(['id' => 're_admin']));
        });

        $this->actingAs($this->admin(), 'admin')
            ->patchJson("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(12, $this->product->fresh()->stock);
    }

    public function test_admin_cancel_keeps_the_order_when_the_refund_fails(): void
    {
        $order = $this->paidOrder();
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('refundPayment')->andThrow(new \RuntimeException('Stripe is down'));
        });

        $this->actingAs($this->admin(), 'admin')
            ->patchJson("/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertEquals('confirmed', $order->fresh()->status);
        $this->assertEquals(10, $this->product->fresh()->stock);
    }

    /**
     * Swap the Stripe service for a partial mock: Stripe API calls are faked, verification logic stays real.
     */
    private function fakeStripe(callable $expectations): void
    {
        $this->partialMock(StripePayments::class, function (MockInterface $mock) use ($expectations) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $expectations($mock);
        });
    }

    private function newIntent(string $id): PaymentIntent
    {
        return PaymentIntent::constructFrom(['id' => $id, 'status' => 'requires_payment_method', 'client_secret' => "{$id}_secret"]);
    }

    /**
     * A succeeded PaymentIntent that exactly pays the given checkout.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function paidIntent(Checkout $checkout, array $overrides = []): PaymentIntent
    {
        return PaymentIntent::constructFrom(array_merge([
            'id' => $checkout->stripe_payment_id,
            'status' => 'succeeded',
            'amount_received' => (int) round((float) $checkout->total * 100),
            'currency' => 'cad',
            'client_secret' => "{$checkout->stripe_payment_id}_secret",
            'metadata' => ['checkout_id' => (string) $checkout->id],
        ], $overrides));
    }

    /**
     * A checkout holding 2 units of the test product ($20.00); its stock counts as already reserved.
     */
    private function checkoutFor(User $user, bool $expired = false): Checkout
    {
        $factory = Checkout::factory()->holding($this->product, 2)->for($user);

        return ($expired ? $factory->expired() : $factory)->create();
    }

    /**
     * A paid order that reserved 2 units of the test product.
     */
    private function paidOrder(): Order
    {
        return Order::factory()->create([
            'status' => 'confirmed',
            'stripe_payment_id' => 'pi_paid_order',
            'reserved_stock' => [$this->product->id => 2],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(int $quantity = 2): array
    {
        return [
            'items' => [[
                'id' => $this->product->id,
                'name' => $this->product->name,
                'price' => 10.00,
                'quantity' => $quantity,
            ]],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
        ];
    }

    private function admin(): Admin
    {
        $this->seed(AdminSeeder::class);

        return Admin::where('email', 'admin@masalamart.com')->first();
    }
}
