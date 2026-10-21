<?php

namespace Tests\Feature;

use App\Events\OrderItemsPackedUpdated;
use App\Events\OrderStatusUpdated;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Services\StripePayments;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Stripe\Refund;
use Tests\TestCase;

class AdminOrderItemRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_admin_can_refund_single_out_of_stock_item_and_zero_product_inventory(): void
    {
        Event::fake([OrderItemsPackedUpdated::class, OrderStatusUpdated::class]);

        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $oosProduct = Product::factory()->create(['name' => 'Royal Basmati Rice', 'price' => 12.99, 'stock' => 4]);
        $inStockProduct = Product::factory()->create(['name' => 'Tata Salt', 'price' => 2.50, 'stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 15.49,
            'total' => 15.49,
            'stripe_payment_id' => 'pi_test_partial_123',
            'reserved_stock' => [
                $oosProduct->id => 1,
                $inStockProduct->id => 1,
            ],
        ]);

        $item1 = $order->items()->create([
            'product_id' => $oosProduct->id,
            'name' => $oosProduct->name,
            'unit_price' => 12.99,
            'quantity' => 1,
            'total_price' => 12.99,
            'is_packed' => true,
        ]);

        $item2 = $order->items()->create([
            'product_id' => $inStockProduct->id,
            'name' => $inStockProduct->name,
            'unit_price' => 2.50,
            'quantity' => 1,
            'total_price' => 2.50,
            'is_packed' => true,
        ]);

        $mockPayments = $this->mock(StripePayments::class);
        $mockPayments->shouldReceive('isEnabled')->andReturn(true);
        $mockPayments->shouldReceive('refundPartialPayment')
            ->once()
            ->withArgs(function (string $pi, int $cents, string $idempotencyKey, array $meta) use ($order, $item1) {
                return $pi === 'pi_test_partial_123'
                    && $cents === 1299
                    && $idempotencyKey === "order-{$order->id}-item-{$item1->id}-refund-qty-1"
                    && $meta['order_item_id'] === (string) $item1->id;
            })
            ->andReturn(Refund::constructFrom(['id' => 're_test_123', 'amount' => 1299, 'status' => 'succeeded']));

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item1->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Damaged bag on shelf',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', 'packing')
            ->assertJsonPath('order.refunded_total', 12.99);

        $this->assertEquals(1, $item1->fresh()->refunded_quantity);
        $this->assertEquals(12.99, (float) $item1->fresh()->refunded_amount);
        $this->assertEquals('Damaged bag on shelf', $item1->fresh()->refund_reason);
        $this->assertFalse($item1->fresh()->is_packed);

        // Storefront product stock is zeroed so new shoppers cannot buy the OOS item
        $this->assertEquals(0, $oosProduct->fresh()->stock);
        $this->assertEquals(10, $inStockProduct->fresh()->stock);

        // Order remains in packing with updated refunded_total and audit note
        $freshOrder = $order->fresh();
        $this->assertEquals('packing', $freshOrder->status);
        $this->assertEquals(12.99, (float) $freshOrder->refunded_total);
        $this->assertStringContainsString('Refunded 1× Royal Basmati Rice ($12.99)', (string) $freshOrder->notes);

        Event::assertDispatched(OrderItemsPackedUpdated::class);
        Event::assertNotDispatched(OrderStatusUpdated::class);
    }

    public function test_admin_can_refund_partial_quantity_of_multi_unit_item(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $product = Product::factory()->create(['name' => 'Amul Butter', 'price' => 5.00, 'stock' => 2]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 15.00,
            'total' => 15.00,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 5.00,
            'quantity' => 3,
            'total_price' => 15.00,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Only 2 packs left on shelf',
            ]);

        $response->assertOk()
            ->assertJsonPath('order.status', 'packing')
            ->assertJsonPath('order.refunded_total', 5);

        $this->assertEquals(1, $item->fresh()->refunded_quantity);
        $this->assertEquals(5.00, (float) $item->fresh()->refunded_amount);
        $this->assertEquals(0, $product->fresh()->stock);
    }

    public function test_refunding_last_remaining_item_automatically_cancels_order(): void
    {
        Event::fake([OrderItemsPackedUpdated::class, OrderStatusUpdated::class]);

        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $product = Product::factory()->create(['name' => 'Alphonso Mangoes', 'price' => 19.99, 'stock' => 1]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 19.99,
            'total' => 19.99,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 19.99,
            'quantity' => 1,
            'total_price' => 19.99,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.refunded_total', 19.99);

        $this->assertEquals('cancelled', $order->fresh()->status);
        Event::assertDispatched(OrderStatusUpdated::class);
    }

    public function test_cancelling_order_after_partial_item_refund_only_refunds_remaining_balance_and_restocks_active_items(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $oosProduct = Product::factory()->create(['name' => 'OOS Paneer', 'price' => 6.00, 'stock' => 0]);
        $goodProduct = Product::factory()->create(['name' => 'Good Atta', 'price' => 14.00, 'stock' => 5]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 20.00,
            'total' => 20.00,
            'refunded_total' => 6.00,
            'stripe_payment_id' => 'pi_test_cancel_rem_456',
            'reserved_stock' => [
                $oosProduct->id => 0,
                $goodProduct->id => 1,
            ],
        ]);

        $order->items()->create([
            'product_id' => $oosProduct->id,
            'name' => $oosProduct->name,
            'unit_price' => 6.00,
            'quantity' => 1,
            'refunded_quantity' => 1,
            'total_price' => 6.00,
            'refunded_amount' => 6.00,
        ]);

        $order->items()->create([
            'product_id' => $goodProduct->id,
            'name' => $goodProduct->name,
            'unit_price' => 14.00,
            'quantity' => 1,
            'refunded_quantity' => 0,
            'total_price' => 14.00,
            'refunded_amount' => 0.00,
        ]);

        $mockPayments = $this->mock(StripePayments::class);
        $mockPayments->shouldReceive('isEnabled')->andReturn(true);
        $mockPayments->shouldReceive('refundPartialPayment')
            ->once()
            ->withArgs(function (string $pi, int $cents, string $idempotencyKey) use ($order) {
                return $pi === 'pi_test_cancel_rem_456'
                    && $cents === 1400
                    && $idempotencyKey === "order-{$order->id}-cancel-remaining-refund";
            })
            ->andReturn(Refund::constructFrom(['id' => 're_test_rem_456', 'amount' => 1400, 'status' => 'succeeded']));

        $response = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), [
                'status' => 'cancelled',
            ]);

        $response->assertOk();
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(20.00, (float) $order->fresh()->refunded_total);

        // OOS product remains at 0 stock; good product is restocked from 5 to 6
        $this->assertEquals(0, $oosProduct->fresh()->stock);
        $this->assertEquals(6, $goodProduct->fresh()->stock);
    }
}
