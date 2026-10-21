<?php

namespace Tests\Feature;

use App\Events\OrderItemsPackedUpdated;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StripePayments;
use Database\Seeders\AdminSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Stripe\Refund;
use Tests\TestCase;

class SevenBugFixesVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_bug_1_refund_is_not_counted_twice_when_webhook_arrives_before_admin_refund_finishes(): void
    {
        Event::fake([OrderItemsPackedUpdated::class, OrderStatusUpdated::class]);

        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $product1 = Product::factory()->create(['price' => 10.00, 'stock' => 5]);
        $product2 = Product::factory()->create(['price' => 15.00, 'stock' => 5]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 25.00,
            'total' => 25.00,
            'refunded_total' => 0.00,
            'stripe_payment_id' => 'pi_race_refund_10',
        ]);

        $item1 = $order->items()->create([
            'product_id' => $product1->id,
            'name' => $product1->name,
            'unit_price' => 10.00,
            'quantity' => 1,
            'total_price' => 10.00,
        ]);

        $order->items()->create([
            'product_id' => $product2->id,
            'name' => $product2->name,
            'unit_price' => 15.00,
            'quantity' => 1,
            'total_price' => 15.00,
        ]);

        $mockPayments = $this->mock(StripePayments::class);
        $mockPayments->shouldReceive('isEnabled')->andReturn(true);
        $mockPayments->shouldReceive('refundPartialPayment')
            ->once()
            ->andReturnUsing(function () {
                // Simulate Stripe webhook arriving right after Stripe processes the $10 refund,
                // before AdminOrderController::refundOrderItem enters its DB transaction.
                $this->postJson(route('stripe.webhook'), [
                    'type' => 'charge.refunded',
                    'data' => [
                        'object' => [
                            'payment_intent' => 'pi_race_refund_10',
                            'refunded' => false,
                            'amount_refunded' => 1000,
                        ],
                    ],
                ])->assertOk();

                return Refund::constructFrom(['id' => 're_race_10', 'amount' => 1000, 'status' => 'succeeded']);
            });

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item1->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Out of stock',
            ]);

        $response->assertOk()
            ->assertJsonPath('order.refunded_total', 10);

        $this->assertEquals(10.00, (float) $order->fresh()->refunded_total);
        $this->assertEquals(10.00, (float) $item1->fresh()->refunded_amount);
    }

    public function test_bug_2_cancelled_order_cannot_reopen_during_concurrent_status_update(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $product = Product::factory()->create(['stock' => 5]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 20.00,
            'reserved_stock' => [$product->id => 2],
        ]);

        // Hydrate an initial Order instance while status is 'confirmed' (simulating route model binding)
        $staleBoundOrder = Order::findOrFail($order->id);
        $this->assertSame('confirmed', $staleBoundOrder->status);

        // Concurrent request cancels and restocks the order in the database before updateStatus locks the row
        $order->cancelAndRestock('Cancelled concurrently', 20.00);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock);

        $request = Request::create(
            route('admin.orders.status.update', $order->id),
            'PATCH',
            ['status' => 'packing']
        );

        $controller = app(AdminOrderController::class);

        try {
            $controller->updateStatus($request, $staleBoundOrder, app(StripePayments::class));
            $this->fail('Expected ValidationException when attempting to reopen a concurrently cancelled order.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    private function configureReverbBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    public function test_bug_3_admin_without_manage_orders_permission_cannot_subscribe_to_admin_orders_channel(): void
    {
        $this->configureReverbBroadcaster();

        $catalogAdmin = Admin::create([
            'name' => 'Catalog Only Admin',
            'email' => 'catalog-only@masalamart.com',
            'password' => Hash::make('password123'),
        ]);
        $catalogRole = Role::findOrCreate('Catalog Specialist', 'admin');
        $catalogRole->syncPermissions(['manage products']);
        $catalogAdmin->assignRole($catalogRole);

        $this->actingAs($catalogAdmin, 'admin')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-admin.orders',
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_bug_3_admin_with_manage_orders_permission_can_subscribe_to_admin_orders_channel(): void
    {
        $this->configureReverbBroadcaster();

        $orderAdmin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();

        $this->actingAs($orderAdmin, 'admin')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-admin.orders',
                'socket_id' => '1234.5678',
            ])
            ->assertOk();
    }

    public function test_bug_4_customer_order_updates_use_private_channels_and_enforce_ownership(): void
    {
        $this->configureReverbBroadcaster();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => 'packing',
        ]);

        $channelKey = ltrim($order->order_number, '#');

        $packedEvent = new OrderItemsPackedUpdated($order);
        $statusEvent = new OrderStatusUpdated($order);

        foreach ([$packedEvent, $statusEvent] as $event) {
            $channels = $event->broadcastOn();
            $orderChannel = collect($channels)->first(fn ($ch) => str_contains($ch->name, $channelKey));

            $this->assertInstanceOf(PrivateChannel::class, $orderChannel);
            $this->assertSame('private-orders.'.$channelKey, $orderChannel->name);
        }

        // Owner is authorized on private-orders.{orderNumber}
        $this->actingAs($owner, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-orders.'.$channelKey,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();

        // Stranger is denied on private-orders.{orderNumber}
        $this->actingAs($stranger, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-orders.'.$channelKey,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_bug_5_cancelling_through_item_refunds_also_refunds_delivery_fee(): void
    {
        Event::fake([OrderItemsPackedUpdated::class, OrderStatusUpdated::class]);

        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $product = Product::factory()->create(['price' => 20.00, 'stock' => 3]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'fulfillment_type' => 'Home Delivery',
            'subtotal' => 20.00,
            'delivery_fee' => 5.00,
            'total' => 25.00,
            'refunded_total' => 0.00,
            'stripe_payment_id' => 'pi_delivery_order_25',
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 20.00,
            'quantity' => 1,
            'total_price' => 20.00,
        ]);

        $mockPayments = $this->mock(StripePayments::class);
        $mockPayments->shouldReceive('isEnabled')->andReturn(true);
        $mockPayments->shouldReceive('refundPartialPayment')
            ->once()
            ->withArgs(function (string $pi, int $cents) {
                return $pi === 'pi_delivery_order_25' && $cents === 2500;
            })
            ->andReturn(Refund::constructFrom(['id' => 're_deliv_25', 'amount' => 2500, 'status' => 'succeeded']));

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Out of stock',
            ]);

        $response->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.refunded_total', 25);

        $order->refresh();
        $item->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(25.00, (float) $order->refunded_total);
        $this->assertEquals(20.00, (float) $item->refunded_amount);
    }

    public function test_bug_6_full_stripe_refund_on_completed_order_records_refund_and_deducts_points(): void
    {
        $user = User::factory()->create(); // Starts with 100 welcome bonus points

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'subtotal' => 30.00,
            'total' => 30.00,
            'refunded_total' => 0.00,
            'stripe_payment_id' => 'pi_completed_full_refund_30',
        ]);

        $this->assertSame(30, (int) $order->fresh()->points_earned);
        $this->assertSame(130, (int) $user->fresh()->reward_points);

        $response = $this->postJson(route('stripe.webhook'), [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'payment_intent' => 'pi_completed_full_refund_30',
                    'refunded' => true,
                    'amount_refunded' => 3000,
                ],
            ],
        ]);

        $response->assertOk();

        $order->refresh();
        $user->refresh();

        $this->assertSame('completed', $order->status);
        $this->assertEquals(30.00, (float) $order->refunded_total);
        $this->assertSame(0, (int) $order->points_earned);
        $this->assertSame(100, (int) $user->reward_points);
        $this->assertStringContainsString('[REFUNDED ON STRIPE: $30.00]', (string) $order->notes);
    }

    public function test_bug_7_pickup_slot_generation_preserves_configured_minutes(): void
    {
        $setting = StoreSetting::current();
        $setting->update([
            'pickup_slot_start_time' => '09:30',
            'pickup_slot_end_time' => '10:30',
            'pickup_slot_duration_minutes' => 60,
            'pickup_slots' => null,
        ]);

        $slots = $setting->fresh()->available_pickup_slots;

        $this->assertCount(1, $slots);
        $this->assertSame('9:30 AM – 10:30 AM', $slots[0]['label']);
    }
}
