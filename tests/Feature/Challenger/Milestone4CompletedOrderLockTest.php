<?php

namespace Tests\Feature\Challenger;

use App\Http\Controllers\Admin\AdminOrderController;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Services\StripePayments;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Challenger Test Suite for Milestone 4 (ADM-01):
 * Completed Order Cancellation Lock & State Machine Hardening.
 */
class Milestone4CompletedOrderLockTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
    }

    /**
     * EMPIRICAL TEST 1:
     * HTTP PATCH /admin/orders/{id}/status with status='cancelled' on a completed order
     * returns 422 Unprocessable Entity with exact error message and does not mutate order or stock.
     */
    public function test_patch_status_cancelled_on_completed_order_is_rejected_with_422(): void
    {
        $product = Product::factory()->create(['stock' => 20]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 60.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 2],
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 30.00,
            'quantity' => 2,
            'total_price' => 60.00,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'cancelled']
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(
            'Completed orders cannot be cancelled or refunded.',
            $response->json('errors.status.0')
        );

        // Verify database state: order remains completed, stock unchanged, refunded_total untouched
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(0.00, (float) $order->fresh()->refunded_total);
    }

    /**
     * EMPIRICAL TEST 2:
     * HTTP PATCH /admin/orders/{id}/status via standard HTML form redirect
     * preserves error bag and rejects cancellation on completed orders.
     */
    public function test_patch_status_cancelled_on_completed_order_html_redirect_rejection(): void
    {
        $product = Product::factory()->create(['stock' => 15]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 45.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 1],
        ]);

        $response = $this->actingAs($this->admin, 'admin')->patch(
            route('admin.orders.status.update', $order->id),
            ['status' => 'cancelled']
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['status' => 'Completed orders cannot be cancelled or refunded.']);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(15, $product->fresh()->stock);
    }

    /**
     * EMPIRICAL TEST 3:
     * Verify cancelOrder() method on AdminOrderController throws 422 HttpException
     * when executed against a completed order.
     */
    public function test_admin_order_controller_cancel_order_throws_422_on_completed_order(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 100.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 2],
        ]);

        $controller = app(AdminOrderController::class);
        $mockPayments = Mockery::mock(StripePayments::class);

        $reflection = new ReflectionMethod(AdminOrderController::class, 'cancelOrder');

        try {
            $reflection->invoke($controller, $order, $mockPayments);
            $this->fail('Expected HttpException 422 was not thrown.');
        } catch (\Throwable $e) {
            $previous = $e->getPrevious() ?: $e;
            $this->assertInstanceOf(HttpException::class, $previous);
            $this->assertSame(422, $previous->getStatusCode());
            $this->assertSame('Completed orders cannot be cancelled.', $previous->getMessage());
        }

        // Verify order state unchanged
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }

    /**
     * EMPIRICAL TEST 4:
     * Attempting to hit an unmapped POST /admin/orders/{id}/cancel returns 404/405,
     * confirming no backdoor unauthenticated or unmanaged route exists.
     */
    public function test_post_orders_cancel_route_returns_404_or_405(): void
    {
        $order = Order::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($this->admin, 'admin')->postJson("/admin/orders/{$order->id}/cancel");

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    /**
     * EMPIRICAL TEST 5:
     * Direct Order::cancelAndRestock() on a completed order:
     * returns false, does not restock products, does not change status, does not alter refunded_total,
     * and does not append cancellation notes.
     */
    public function test_order_cancel_and_restock_returns_false_on_completed_order(): void
    {
        $product1 = Product::factory()->create(['stock' => 5]);
        $product2 = Product::factory()->create(['stock' => 8]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 150.00,
            'refunded_total' => 0.00,
            'notes' => 'Customer picked up at 5pm.',
            'reserved_stock' => [
                $product1->id => 3,
                $product2->id => 2,
            ],
        ]);

        $result = $order->cancelAndRestock('Customer requested return', 150.00);

        $this->assertFalse($result);

        $freshOrder = $order->fresh();
        $this->assertSame('completed', $freshOrder->status);
        $this->assertSame(0.00, (float) $freshOrder->refunded_total);
        $this->assertSame('Customer picked up at 5pm.', $freshOrder->notes);

        // Verify product stocks were NOT increased (no phantom restock)
        $this->assertSame(5, $product1->fresh()->stock);
        $this->assertSame(8, $product2->fresh()->stock);
    }

    /**
     * EMPIRICAL TEST 6:
     * Direct Order::cancelAndRestock() on an already cancelled order:
     * returns false, does not restock again (idempotency check), and does not overwrite refunded_total.
     */
    public function test_order_cancel_and_restock_returns_false_on_already_cancelled_order(): void
    {
        $product = Product::factory()->create(['stock' => 12]);

        $order = Order::factory()->create([
            'status' => 'cancelled',
            'total' => 50.00,
            'refunded_total' => 50.00,
            'notes' => 'Original cancellation note.',
            'reserved_stock' => [$product->id => 4],
        ]);

        $result = $order->cancelAndRestock('Second cancellation attempt', 75.00);

        $this->assertFalse($result);

        $freshOrder = $order->fresh();
        $this->assertSame('cancelled', $freshOrder->status);
        $this->assertSame(50.00, (float) $freshOrder->refunded_total);
        $this->assertSame('Original cancellation note.', $freshOrder->notes);

        // Stock was not doubled
        $this->assertSame(12, $product->fresh()->stock);
    }

    /**
     * EMPIRICAL TEST 7:
     * Order::cancelAndRestock() with legacy order (reserved_stock null, line items present):
     * returns false on completed order and does not restock items.
     */
    public function test_order_cancel_and_restock_legacy_order_without_reserved_stock_array(): void
    {
        $product = Product::factory()->create(['stock' => 7]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 35.00,
            'refunded_total' => 0.00,
            'reserved_stock' => null,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 35.00,
            'quantity' => 1,
            'total_price' => 35.00,
        ]);

        $result = $order->cancelAndRestock('Legacy return attempt', 35.00);

        $this->assertFalse($result);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock);
    }

    /**
     * EMPIRICAL TEST 8:
     * Active orders in normal statuses (confirmed, packing, ready_for_pickup) CAN still be cancelled and restocked properly.
     */
    public function test_normal_orders_can_still_be_cancelled_and_restocked(): void
    {
        $mockPayments = Mockery::mock(StripePayments::class);
        $mockPayments->shouldReceive('isEnabled')->andReturn(false);
        $this->app->instance(StripePayments::class, $mockPayments);

        // 1. Confirmed order
        $p1 = Product::factory()->create(['stock' => 10]);
        $orderConfirmed = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 40.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$p1->id => 2],
        ]);

        $responseConfirmed = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $orderConfirmed->id),
            ['status' => 'cancelled']
        );

        $responseConfirmed->assertStatus(200);
        $this->assertSame('cancelled', $orderConfirmed->fresh()->status);
        $this->assertSame(12, $p1->fresh()->stock);
        $this->assertSame(40.00, (float) $orderConfirmed->fresh()->refunded_total);

        // 2. Packing order
        $p2 = Product::factory()->create(['stock' => 15]);
        $orderPacking = Order::factory()->create([
            'status' => 'packing',
            'total' => 25.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$p2->id => 3],
        ]);

        $responsePacking = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $orderPacking->id),
            ['status' => 'cancelled']
        );

        $responsePacking->assertStatus(200);
        $this->assertSame('cancelled', $orderPacking->fresh()->status);
        $this->assertSame(18, $p2->fresh()->stock);

        // 3. Ready for pickup order
        $p3 = Product::factory()->create(['stock' => 3]);
        $orderPickup = Order::factory()->create([
            'status' => 'ready_for_pickup',
            'total' => 18.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$p3->id => 1],
        ]);

        $responsePickup = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $orderPickup->id),
            ['status' => 'cancelled']
        );

        $responsePickup->assertStatus(200);
        $this->assertSame('cancelled', $orderPickup->fresh()->status);
        $this->assertSame(4, $p3->fresh()->stock);

        // 4. Direct cancelAndRestock on confirmed order
        $p4 = Product::factory()->create(['stock' => 20]);
        $orderDirect = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 80.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$p4->id => 5],
        ]);

        $directResult = $orderDirect->cancelAndRestock('Admin manual cancel', 80.00);

        $this->assertTrue($directResult);
        $this->assertSame('cancelled', $orderDirect->fresh()->status);
        $this->assertSame(25, $p4->fresh()->stock);
        $this->assertSame(80.00, (float) $orderDirect->fresh()->refunded_total);
    }

    /**
     * EMPIRICAL TEST 9:
     * Cancelled orders cannot be reopened to confirmed, packing, ready_for_pickup, or completed.
     */
    public function test_cancelled_orders_cannot_be_reopened(): void
    {
        $order = Order::factory()->create(['status' => 'cancelled']);

        foreach (['confirmed', 'packing', 'ready_for_pickup', 'completed'] as $targetStatus) {
            $response = $this->actingAs($this->admin, 'admin')->patchJson(
                route('admin.orders.status.update', $order->id),
                ['status' => $targetStatus]
            );

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);

            $this->assertSame(
                'A cancelled order cannot be reopened: its stock was returned and any payment refunded.',
                $response->json('errors.status.0')
            );
        }

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    /**
     * EMPIRICAL TEST 10:
     * Item refund endpoint cannot be used on a completed order as a backdoor to refund/cancel.
     */
    public function test_completed_order_item_cannot_be_refunded(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 50.00,
            'refunded_total' => 0.00,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 2,
            'total_price' => 50.00,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->postJson(
            route('admin.orders.items.refund', ['order' => $order->id, 'item' => $item->id]),
            [
                'quantity' => 1,
                'zero_product_stock' => false,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['item']);

        $this->assertSame(
            'Items can only be refunded for active orders before completion or cancellation.',
            $response->json('errors.item.0')
        );

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(0.00, (float) $order->fresh()->refunded_total);
        $this->assertSame(5, $product->fresh()->stock);
    }

    /**
     * EMPIRICAL TEST 11:
     * Completed order with prior partial refund remains immutable against status cancellation.
     */
    public function test_completed_order_with_prior_partial_refund_cannot_be_cancelled(): void
    {
        $product = Product::factory()->create(['stock' => 14]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 100.00,
            'refunded_total' => 25.00, // Partial refund occurred while order was active
            'reserved_stock' => [$product->id => 2],
        ]);

        $response = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'cancelled']
        );

        $response->assertStatus(422);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(25.00, (float) $order->fresh()->refunded_total);
        $this->assertSame(14, $product->fresh()->stock);
    }
}
