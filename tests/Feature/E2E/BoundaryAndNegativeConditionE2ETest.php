<?php

namespace Tests\Feature\E2E;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BoundaryAndNegativeConditionE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
        $this->seed(AdminSeeder::class);

        $this->customer = User::factory()->create([
            'name' => 'Meera Patel',
            'email' => 'meera@example.com',
        ]);
    }

    public function test_unauthenticated_user_redirected_on_checkout_page_and_cannot_post_checkout(): void
    {
        // GET /checkout redirects to login
        $pageResponse = $this->get('/checkout');
        $pageResponse->assertRedirect('/login');

        // POST /checkout returns 401 Unauthorized
        $product = Product::factory()->create(['price' => 15.00]);
        $postResponse = $this->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1],
            ],
        ]);
        $postResponse->assertUnauthorized();
    }

    public function test_empty_cart_is_rejected_with_validation_error(): void
    {
        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertInvalid(['items']);
        $this->assertEquals(0, Order::count());
    }

    public function test_zero_stock_product_is_rejected(): void
    {
        $outOfStockProduct = Product::factory()->create([
            'name' => 'Moti Pak Sweets',
            'price' => 12.00,
            'stock' => 0,
        ]);

        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [
                ['id' => $outOfStockProduct->id, 'name' => $outOfStockProduct->name, 'price' => 12.00, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error_code.0', 'out_of_stock');
        $this->assertEquals(0, Order::count());
    }

    public function test_insufficient_stock_for_requested_quantity(): void
    {
        $lowStockProduct = Product::factory()->create([
            'name' => 'Alphonso Mango Box',
            'price' => 29.99,
            'stock' => 2,
        ]);

        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [
                ['id' => $lowStockProduct->id, 'name' => $lowStockProduct->name, 'price' => 29.99, 'quantity' => 5],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error_code.0', 'out_of_stock');
        $this->assertEquals(0, Order::count());
        $this->assertEquals(2, $lowStockProduct->fresh()->stock);
    }

    public function test_non_existent_item_id_is_rejected(): void
    {
        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [
                ['id' => 'non-existent-product-id', 'name' => 'Ghost Item', 'price' => 9.99, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ]);

        $response->assertUnprocessable()
            ->assertInvalid(['items.0.id']);
        $this->assertEquals(0, Order::count());
    }

    public function test_pickup_slot_capacity_limit_rejection(): void
    {
        $todayDate = now()->toDateString();
        $slotLabel = "{$todayDate} · 5:00 PM – 6:00 PM";

        StoreSetting::current()->update([
            'max_orders_per_slot' => 2,
            'pickup_slots' => [
                ['id' => '17-18', 'label' => '5:00 PM – 6:00 PM', 'startHour' => 17, 'active' => true],
            ],
        ]);

        // Place 2 orders occupying the slot
        Order::factory()->count(2)->create([
            'pickup_slot' => $slotLabel,
            'status' => 'confirmed',
        ]);

        $product = Product::factory()->create(['stock' => 20]);

        // 3rd order targeting the full slot should be rejected
        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'slot',
            'pickup_date' => $todayDate,
            'pickup_slot' => '5:00 PM – 6:00 PM',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.error_code.0', 'slot_full');
        $this->assertEquals(2, Order::count());
    }

    public function test_past_pickup_time_is_rejected(): void
    {
        $this->travelTo(now()->setTime(17, 0));
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '12:00',
        ]);

        $response->assertUnprocessable()
            ->assertInvalid(['pickup_time']);
        $this->assertEquals(0, Order::count());
    }

    public function test_home_delivery_without_address_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->customer)->postJson('/checkout', [
            'items' => [
                ['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '',
        ]);

        $response->assertUnprocessable()
            ->assertInvalid(['delivery_address']);
        $this->assertEquals(0, Order::count());
    }

    public function test_cancelled_order_cannot_be_reopened(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $order = Order::factory()->create(['status' => 'cancelled']);

        $response = $this->actingAs($admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), [
                'status' => 'packing',
            ]);

        $response->assertUnprocessable()
            ->assertInvalid(['status']);
        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    public function test_completed_order_items_cannot_be_refunded(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $order = Order::factory()->create(['status' => 'completed']);
        $item = $order->items()->create([
            'name' => 'Garam Masala',
            'unit_price' => 5.00,
            'quantity' => 1,
            'total_price' => 5.00,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item->id]), [
                'quantity' => 1,
            ]);

        $response->assertUnprocessable()
            ->assertInvalid(['item']);
    }

    public function test_unauthorized_user_cannot_view_another_users_order_tracking(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $intruder = User::factory()->create(['name' => 'Intruder']);

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'customer_name' => $owner->name,
            'status' => 'confirmed',
        ]);

        $cleanNumber = ltrim($order->order_number, '#');

        // Intruder accessing owner's order tracking page receives 403 Forbidden
        $response = $this->actingAs($intruder)->get("/orders/{$cleanNumber}");
        $response->assertForbidden();
    }

    public function test_admin_permission_boundaries_enforced(): void
    {
        // Create an admin without 'manage products' permission
        $limitedRole = Role::firstOrCreate(['name' => 'dispatcher', 'guard_name' => 'admin']);
        $limitedAdmin = Admin::create([
            'name' => 'Dispatcher Admin',
            'email' => 'dispatcher@masalamart.com',
            'password' => bcrypt('password'),
        ]);
        $limitedAdmin->assignRole($limitedRole);

        // Attempting to access product create returns 403
        $response = $this->actingAs($limitedAdmin, 'admin')->get(route('admin.products.create'));
        $response->assertForbidden();

        // Attempting to store a product returns 403
        $storeResponse = $this->actingAs($limitedAdmin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Unauthorized Product',
            'slug' => 'unauthorized-product',
            'price' => 10.00,
            'category' => 'spices',
        ]);
        $storeResponse->assertForbidden();
    }
}
