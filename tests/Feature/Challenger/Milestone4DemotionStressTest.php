<?php

namespace Tests\Feature\Challenger;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Challenger Stress Harness:
 * Empirically tests terminal state lock on completed orders,
 * demotion rejection, stock preservation, and active order forward lifecycle.
 */
class Milestone4DemotionStressTest extends TestCase
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
     * EMPIRICAL CHALLENGE 1:
     * Attempting to demote or cancel a completed order via PATCH with
     * ['confirmed', 'packing', 'ready_for_pickup', 'cancelled']
     * must return HTTP 422, appropriate validation message, and leave stock/order untouched.
     */
    public function test_all_demotion_and_cancellation_statuses_rejected_on_completed_order(): void
    {
        $product = Product::factory()->create(['stock' => 50]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 120.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 4],
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 30.00,
            'quantity' => 4,
            'total_price' => 120.00,
        ]);

        $statusesToTest = [
            'confirmed' => 'Completed orders cannot be modified or reopened.',
            'packing' => 'Completed orders cannot be modified or reopened.',
            'ready_for_pickup' => 'Completed orders cannot be modified or reopened.',
            'cancelled' => 'Completed orders cannot be cancelled or refunded.',
        ];

        foreach ($statusesToTest as $targetStatus => $expectedErrorMessage) {
            $response = $this->actingAs($this->admin, 'admin')->patchJson(
                route('admin.orders.status.update', $order->id),
                ['status' => $targetStatus]
            );

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);

            $this->assertSame(
                $expectedErrorMessage,
                $response->json('errors.status.0'),
                "Mismatch in expected error message for target status [{$targetStatus}]"
            );

            // Verify order state in DB
            $this->assertSame('completed', $order->fresh()->status);
            $this->assertSame(0.00, (float) $order->fresh()->refunded_total);

            // Verify product stock in DB
            $this->assertSame(50, $product->fresh()->stock, "Product stock modified when attempting transition to [{$targetStatus}]");
        }
    }

    /**
     * EMPIRICAL CHALLENGE 2:
     * HTML form redirect on completed order for each invalid target status.
     */
    public function test_html_form_redirect_rejects_demotion_on_completed_order(): void
    {
        $product = Product::factory()->create(['stock' => 25]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 50.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 2],
        ]);

        $statusesToTest = [
            'confirmed' => 'Completed orders cannot be modified or reopened.',
            'packing' => 'Completed orders cannot be modified or reopened.',
            'ready_for_pickup' => 'Completed orders cannot be modified or reopened.',
            'cancelled' => 'Completed orders cannot be cancelled or refunded.',
        ];

        foreach ($statusesToTest as $targetStatus => $expectedErrorMessage) {
            $response = $this->actingAs($this->admin, 'admin')->patch(
                route('admin.orders.status.update', $order->id),
                ['status' => $targetStatus]
            );

            $response->assertStatus(302);
            $response->assertSessionHasErrors(['status' => $expectedErrorMessage]);

            $this->assertSame('completed', $order->fresh()->status);
            $this->assertSame(25, $product->fresh()->stock);
        }
    }

    /**
     * EMPIRICAL CHALLENGE 3:
     * Multi-product order: verify stock is NEVER restocked across successive adversarial attacks.
     */
    public function test_multi_product_stock_preservation_under_repeated_demotion_attacks(): void
    {
        $p1 = Product::factory()->create(['stock' => 10]);
        $p2 = Product::factory()->create(['stock' => 20]);
        $p3 = Product::factory()->create(['stock' => 30]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 200.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [
                $p1->id => 3,
                $p2->id => 5,
                $p3->id => 2,
            ],
        ]);

        $order->items()->createMany([
            ['product_id' => $p1->id, 'name' => $p1->name, 'unit_price' => 20.0, 'quantity' => 3, 'total_price' => 60.0],
            ['product_id' => $p2->id, 'name' => $p2->name, 'unit_price' => 20.0, 'quantity' => 5, 'total_price' => 100.0],
            ['product_id' => $p3->id, 'name' => $p3->name, 'unit_price' => 20.0, 'quantity' => 2, 'total_price' => 40.0],
        ]);

        // Blast order with multiple invalid status transitions
        foreach (['packing', 'confirmed', 'cancelled', 'ready_for_pickup', 'cancelled', 'confirmed'] as $attempt) {
            $this->actingAs($this->admin, 'admin')->patchJson(
                route('admin.orders.status.update', $order->id),
                ['status' => $attempt]
            )->assertStatus(422);

            $this->assertSame(10, $p1->fresh()->stock, "Product 1 stock mutated during attempt: {$attempt}");
            $this->assertSame(20, $p2->fresh()->stock, "Product 2 stock mutated during attempt: {$attempt}");
            $this->assertSame(30, $p3->fresh()->stock, "Product 3 stock mutated during attempt: {$attempt}");
            $this->assertSame('completed', $order->fresh()->status);
            $this->assertSame(0.00, (float) $order->fresh()->refunded_total);
        }
    }

    /**
     * EMPIRICAL CHALLENGE 4:
     * Active orders transition forward cleanly through the entire lifecycle:
     * confirmed -> packing -> ready_for_pickup -> completed
     * And once completed, are immediately locked against demotion.
     */
    public function test_normal_active_orders_transition_forward_cleanly(): void
    {
        $product = Product::factory()->create(['stock' => 100]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 75.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 3],
        ]);

        // 1. confirmed -> packing
        $res1 = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'packing']
        );
        $res1->assertOk()->assertJsonPath('success', true);
        $this->assertSame('packing', $order->fresh()->status);
        $this->assertSame(100, $product->fresh()->stock);

        // 2. packing -> ready_for_pickup
        $res2 = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'ready_for_pickup']
        );
        $res2->assertOk()->assertJsonPath('success', true);
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertSame(100, $product->fresh()->stock);

        // 3. ready_for_pickup -> completed
        $res3 = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'completed']
        );
        $res3->assertOk()->assertJsonPath('success', true);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(100, $product->fresh()->stock);

        // 4. Now that it is completed, verify it cannot be moved backwards to ready_for_pickup
        $res4 = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'ready_for_pickup']
        );
        $res4->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
        $this->assertSame('Completed orders cannot be modified or reopened.', $res4->json('errors.status.0'));
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(100, $product->fresh()->stock);
    }

    /**
     * EMPIRICAL CHALLENGE 5:
     * Idempotent status update on completed order (completed -> completed) returns 200 OK.
     */
    public function test_completed_order_idempotent_self_update_succeeds(): void
    {
        $order = Order::factory()->create([
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'completed']
        );

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame('completed', $order->fresh()->status);
    }
}
