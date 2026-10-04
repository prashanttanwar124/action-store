<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Events\OrderStatusUpdated;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_guest_cannot_access_admin_orders(): void
    {
        $response = $this->get(route('admin.orders.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_orders_index_with_stats(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $order = Order::factory()->create([
            'order_number' => '#MM-12345',
            'customer_name' => 'Aarav Patel',
            'status' => 'confirmed',
            'total' => 45.50,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', '#MM-12345')
            ->has('stats.total_today')
            ->has('stats.confirmed')
            ->has('stats.packing')
            ->has('stats.ready_for_pickup')
        );
    }

    public function test_admin_can_update_order_status_and_broadcasts_reverb_event(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $order = Order::factory()->create([
            'order_number' => '#MM-99999',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->patch(route('admin.orders.status.update', $order->id), [
                'status' => 'packing',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'packing',
        ]);

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id && $event->order->status === 'packing';
        });

        // Test Axios/JSON request
        $jsonResponse = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), [
                'status' => 'ready_for_pickup',
            ]);

        $jsonResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', 'ready_for_pickup');
    }

    public function test_customer_checkout_broadcasts_order_placed_reverb_event(): void
    {
        Event::fake([OrderPlaced::class]);

        $product = Product::factory()->create([
            'name' => 'Tata Sampann Turmeric Powder',
            'price' => 3.99,
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 3.99,
                    'quantity' => 2,
                    'size' => '200 g',
                    'image' => '/images/products/haldi.jpg',
                ],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Today 4–5 pm',
            'pickup_location' => 'Masala Mart — Edison',
            'customer_name' => 'Kavita Sharma',
            'customer_email' => 'kavita@example.com',
            'customer_phone' => '+1 (555) 789-0123',
        ];

        $response = $this->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Kavita Sharma',
            'subtotal' => 7.98,
        ]);

        Event::assertDispatched(OrderPlaced::class, function ($event) {
            return $event->order->customer_name === 'Kavita Sharma'
                && $event->order->items->count() === 1;
        });
    }

    public function test_customer_can_view_live_order_tracking_page(): void
    {
        $order = Order::factory()->create([
            'order_number' => '#MM-77777',
            'customer_name' => 'Sunil Verma',
            'status' => 'ready_for_pickup',
            'total' => 24.50,
        ]);

        $response = $this->withSession(['placed_order_numbers' => ['#MM-77777']])
            ->get('/orders/'.urlencode('#MM-77777'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrderTracking')
            ->where('order.order_number', '#MM-77777')
            ->where('order.status', 'ready_for_pickup')
        );
    }

    public function test_stranger_cannot_view_order_tracking_without_ownership(): void
    {
        Order::factory()->create([
            'order_number' => '#MM-99999',
            'customer_name' => 'Private Customer',
            'status' => 'ready_for_pickup',
            'total' => 50.00,
        ]);

        $response = $this->get('/orders/'.urlencode('#MM-99999'));

        $response->assertStatus(403);
    }
}
