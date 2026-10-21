<?php

namespace Tests\Feature\E2E;

use App\Events\OrderItemsPackedUpdated;
use App\Events\OrderPlaced;
use App\Events\OrderStatusUpdated;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderLifecycleE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
        $this->seed(AdminSeeder::class);
    }

    public function test_complete_real_world_buyer_to_admin_lifecycle(): void
    {
        Event::fake([OrderPlaced::class, OrderStatusUpdated::class, OrderItemsPackedUpdated::class]);

        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();

        // -------------------------------------------------------------
        // Step 1: Customer registers a new account
        // -------------------------------------------------------------
        $regResponse = $this->post('/register', [
            'name' => 'Siddharth Roy',
            'email' => 'siddharth@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
        $regResponse->assertRedirect(route('dashboard', absolute: false));

        $customer = User::where('email', 'siddharth@example.com')->firstOrFail();
        $this->assertEquals(100, $customer->reward_points);
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $customer->id,
            'points' => 100,
            'description' => 'Welcome bonus',
        ]);

        // -------------------------------------------------------------
        // Step 2: Customer browses storefront, searches and checks stock
        // -------------------------------------------------------------
        $paneer = Product::factory()->create([
            'name' => 'Fresh Malai Paneer 400g',
            'slug' => 'fresh-malai-paneer',
            'price' => 6.50,
            'stock' => 10,
            'category' => 'dairy',
        ]);

        $chilli = Product::factory()->create([
            'name' => 'Green Chillies 200g',
            'slug' => 'green-chillies',
            'price' => 2.50,
            'stock' => 15,
            'category' => 'produce',
        ]);

        // Search catalog
        $searchResponse = $this->actingAs($customer)->get('/search?q=Paneer');
        $searchResponse->assertOk();
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'fresh-malai-paneer')
        );

        // Cart live stock check
        $stockCheck = $this->actingAs($customer)->postJson('/cart/stock-check', [
            'ids' => [(string) $paneer->id, (string) $chilli->id],
        ]);
        $stockCheck->assertOk()
            ->assertJsonPath('products.0.stock', 10)
            ->assertJsonPath('products.1.stock', 15);

        // -------------------------------------------------------------
        // Step 3: Customer places store pickup order
        // -------------------------------------------------------------
        // Paneer: 2 x 6.50 = 13.00, Chilli: 1 x 2.50 = 2.50 -> Subtotal = 15.50
        $checkoutPayload = [
            'items' => [
                ['id' => $paneer->id, 'name' => $paneer->name, 'price' => 6.50, 'quantity' => 2],
                ['id' => $chilli->id, 'name' => $chilli->name, 'price' => 2.50, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
            'customer_phone' => '4165550188',
            'notes' => 'Ring bell upon arrival',
        ];

        $checkoutResponse = $this->actingAs($customer)->postJson('/checkout', $checkoutPayload);
        $checkoutResponse->assertStatus(201);

        $orderId = $checkoutResponse->json('order.id');
        $orderNumber = $checkoutResponse->json('order.order_number');
        $this->assertNotNull($orderId);
        $this->assertNotNull($orderNumber);

        // Verify order created and inventory decremented
        $order = Order::with('items')->findOrFail($orderId);
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals(15.50, (float) $order->total);
        $this->assertEquals(8, $paneer->fresh()->stock);
        $this->assertEquals(14, $chilli->fresh()->stock);

        Event::assertDispatched(OrderPlaced::class);

        // -------------------------------------------------------------
        // Step 4: Admin manages order in KDS
        // -------------------------------------------------------------
        // Admin views orders index
        $kdsResponse = $this->actingAs($admin, 'admin')->get(route('admin.orders.index'));
        $kdsResponse->assertOk();
        $kdsResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
            ->where('orders.data.0.status', 'confirmed')
        );

        // Admin updates status: confirmed -> packing
        $statusPacking = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), ['status' => 'packing']);
        $statusPacking->assertOk();
        $this->assertEquals('packing', $order->fresh()->status);

        // Admin packs Paneer item
        $paneerItem = $order->items->firstWhere('product_id', $paneer->id);
        $chilliItem = $order->items->firstWhere('product_id', $chilli->id);

        $packPaneer = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.items.packed', $order->id), [
                'item_id' => $paneerItem->id,
                'is_packed' => true,
            ]);
        $packPaneer->assertOk();
        $this->assertTrue($paneerItem->fresh()->is_packed);
        $this->assertFalse($chilliItem->fresh()->is_packed);

        // Chillies found spoiled/OOS on shelf. Admin refunds chillies item ($2.50)
        $refundChilli = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $chilliItem->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Spoiled produce batch',
            ]);
        $refundChilli->assertOk();

        // Chilli shelf inventory zeroed
        $this->assertEquals(0, $chilli->fresh()->stock);
        // Order refunded_total updated
        $this->assertEquals(2.50, (float) $order->fresh()->refunded_total);
        $this->assertEquals(1, $chilliItem->fresh()->refunded_quantity);

        // Admin moves order to ready_for_pickup
        $readyRes = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), ['status' => 'ready_for_pickup']);
        $readyRes->assertOk();
        $this->assertEquals('ready_for_pickup', $order->fresh()->status);

        // -------------------------------------------------------------
        // Step 5: Customer views live tracking page
        // -------------------------------------------------------------
        $cleanNumber = ltrim($orderNumber, '#');
        $trackingResponse = $this->actingAs($customer)->get("/orders/{$cleanNumber}");
        $trackingResponse->assertOk();
        $trackingResponse->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.id', $order->id)
            ->where('order.status', 'ready_for_pickup')
            ->where('order.total', 15.50)
            ->where('order.refunded_total', 2.50)
            ->has('order.items', 2)
        );

        // -------------------------------------------------------------
        // Step 6: Customer picks up order, Admin marks completed
        // -------------------------------------------------------------
        $completedRes = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), ['status' => 'completed']);
        $completedRes->assertOk();
        $this->assertEquals('completed', $order->fresh()->status);

        // -------------------------------------------------------------
        // Step 7: Customer verifies account order history and reward points
        // -------------------------------------------------------------
        // Net spent = 15.50 - 2.50 = 13.00 -> 13 points earned + 100 welcome = 113 points
        $this->assertEquals(113, $customer->fresh()->reward_points);

        $accountResponse = $this->actingAs($customer->refresh())->get('/account');
        $accountResponse->assertOk();
        $accountResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders', 1)
            ->where('dbOrders.0.id', $order->order_number)
            ->where('dbOrders.0.status', 'completed')
            ->where('dbOrders.0.total', 15.50)
            ->where('dbOrders.0.refunded_total', 2.50)
            ->where('auth.user.reward_points', 113)
        );

        // Verify reorder view
        $reorderResponse = $this->actingAs($customer)->get('/reorder');
        $reorderResponse->assertOk();
        $reorderResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
            ->has('dbOrders', 1)
        );
    }
}
