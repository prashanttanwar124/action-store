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
        $this->travelTo(now()->setTime(14, 0));

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

    public function test_complete_auto_refunds_if_pickup_slot_becomes_full_before_confirmation(): void
    {
        $slotLabel = '10:00 AM – 11:00 AM';
        $today = now()->toDateString();
        $slotString = "{$today} · {$slotLabel}";

        StoreSetting::current()->update([
            'max_orders_per_slot' => 1,
        ]);

        $checkout = $this->checkoutFor($this->user);
        $checkout->update(['pickup_slot' => $slotString, 'capacity_slot' => $slotString]);

        // Another customer completed an order in this slot while user was entering payment details
        Order::factory()->create(['pickup_slot' => $slotString, 'status' => 'confirmed']);

        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->once()->andReturn($this->paidIntent($checkout));
            $mock->shouldReceive('refundPayment')->once()->with($checkout->stripe_payment_id)->andReturn(Refund::constructFrom(['id' => 're_slot_full']));
        });

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payment']);
        $this->assertEquals('slot_full_refunded', $response->json('errors.error_code.0'));
        $this->assertStringContainsString('capacity limit', $response->json('errors.payment.0'));
        $this->assertEquals(1, Order::count()); // Only the other customer's order
        $this->assertModelMissing($checkout);
    }

    public function test_resume_checkout_auto_refunds_without_500_if_stock_depleted(): void
    {
        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('createIntentFor')->once()->andReturn($this->newIntent('pi_depleted'));
            $mock->shouldReceive('retrieveIntent')->andReturnUsing(fn () => $this->paidIntent(Checkout::sole()));
            $mock->shouldReceive('refundPayment')->once()->with('pi_depleted')->andReturn(Refund::constructFrom(['id' => 're_depleted']));
        });

        // 1. Create checkout
        $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload())->assertCreated();

        // 2. Stock runs out before resume
        $this->product->update(['stock' => 0]);

        // 3. User clicks pay again / resumes checkout with paid intent
        $response = $this->actingAs($this->user)->postJson('/checkout', $this->checkoutPayload());

        $response->assertStatus(422)->assertJsonValidationErrors(['payment']);
        $this->assertEquals('out_of_stock_refunded', $response->json('errors.error_code.0'));
        $this->assertEquals(0, Order::count());
        $this->assertEquals(0, Checkout::count());
    }

    public function test_complete_checkout_retains_checkout_record_when_refund_fails_and_informs_customer(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $this->product->update(['stock' => 0]);

        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->once()->andReturn($this->paidIntent($checkout));
            $mock->shouldReceive('refundPayment')->once()->with($checkout->stripe_payment_id)->andThrow(new \RuntimeException('Stripe API error'));
        });

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id]);

        $response->assertStatus(422)->assertJsonValidationErrors(['payment']);
        $this->assertEquals('refund_failed', $response->json('errors.error_code.0'));
        $this->assertStringContainsString('contact support if you do not see your refund', $response->json('errors.payment.0'));
        $this->assertModelExists($checkout);
        $this->assertEquals(0, Order::count());
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

    public function test_home_delivery_orders_succeed_even_when_exceeding_max_orders_per_slot(): void
    {
        StoreSetting::current()->update([
            'max_orders_per_slot' => 15,
            'is_delivery_active' => true,
            'delivery_days' => [strtolower(now()->format('l'))],
            'delivery_fee' => 0.00,
        ]);

        // Create 16 historical delivery orders with the default delivery slot label
        Order::factory()->count(16)->create([
            'fulfillment_type' => 'Home Delivery',
            'pickup_slot' => 'Home Delivery · Same Day 5:00 PM – 8:00 PM',
            'status' => 'confirmed',
        ]);

        $this->assertEquals(16, Order::count());

        $checkout = $this->checkoutFor($this->user);
        $checkout->update([
            'fulfillment_type' => 'Home Delivery',
            'pickup_slot' => 'Home Delivery · Same Day 5:00 PM – 8:00 PM',
            'capacity_slot' => null,
            'delivery_address' => '123 Main St, Edison, NJ',
        ]);

        $this->fakeStripe(function (MockInterface $mock) use ($checkout) {
            $mock->shouldReceive('retrieveIntent')->once()->andReturn($this->paidIntent($checkout));
        });

        $response = $this->actingAs($this->user)->postJson('/checkout/complete', ['checkout_id' => $checkout->id]);

        $response->assertOk();
        $this->assertEquals(17, Order::count());
        $this->assertModelMissing($checkout);
    }

    public function test_complete_or_refund_returns_already_handled_when_checkout_missing_and_no_order(): void
    {
        $checkout = $this->checkoutFor($this->user);
        $intent = $this->paidIntent($checkout);

        // Delete checkout (as if a concurrent webhook already handled and removed it)
        $checkout->delete();

        $payments = app(StripePayments::class);
        $result = $payments->completeOrRefund($checkout, $intent);

        $this->assertTrue($result->isAlreadyHandled());
        $this->assertFalse($result->isCompleted());
    }

    public function test_resuming_unpaid_checkout_rejects_stale_pickup_time_and_releases_checkout(): void
    {
        $this->travelTo(now()->setTime(14, 0));
        $store = StoreSetting::current();
        $store->update(['prep_time_minutes' => 15]);

        $this->fakeStripe(function (MockInterface $mock) {
            $mock->shouldReceive('createIntentFor')->once()->andReturn($this->newIntent('pi_stale_schedule_123'));
            $mock->shouldReceive('retrieveIntent')->once()->with('pi_stale_schedule_123')->andReturn($this->newIntent('pi_stale_schedule_123'));
            $mock->shouldReceive('cancelIntent')->once()->with('pi_stale_schedule_123')->andReturn(PaymentIntent::constructFrom(['id' => 'pi_stale_schedule_123', 'status' => 'canceled']));
        });

        $payload = array_replace($this->checkoutPayload(), [
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '14:30',
        ]);

        // 1. Initial checkout at 14:00 with pickup at 14:30 succeeds
        $first = $this->actingAs($this->user)->postJson('/checkout', $payload);
        $first->assertStatus(201);
        $this->assertDatabaseHas('checkouts', ['stripe_payment_id' => 'pi_stale_schedule_123']);

        // 2. Fast forward time past the preparation threshold (e.g. 14:25, leaving only 5 mins prep)
        $this->travelTo(now()->setTime(14, 25));

        // 3. Customer attempts to resume / retry payment with the same stale scheduled time
        $retry = $this->actingAs($this->user)->postJson('/checkout', $payload);

        $retry->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_time']);
        $this->assertEquals('slot_expired', $retry->json('errors.error_code.0'));

        // Checkout must be released
        $this->assertDatabaseMissing('checkouts', ['stripe_payment_id' => 'pi_stale_schedule_123']);
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
