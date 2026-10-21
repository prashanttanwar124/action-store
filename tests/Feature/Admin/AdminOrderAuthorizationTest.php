<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_admin_without_manage_orders_permission_cannot_access_orders_index(): void
    {
        $unauthorizedAdmin = Admin::create([
            'name' => 'Restricted Catalog Admin',
            'email' => 'catalog-only@masalamart.com',
            'password' => Hash::make('password'),
        ]);
        $catalogRole = Role::create(['name' => 'Catalog Specialist', 'guard_name' => 'admin']);
        $catalogRole->givePermissionTo('manage products');
        $unauthorizedAdmin->assignRole($catalogRole);

        $response = $this->actingAs($unauthorizedAdmin, 'admin')
            ->get(route('admin.orders.index'));

        $response->assertStatus(403);
    }

    public function test_admin_without_manage_orders_permission_cannot_update_order_status(): void
    {
        $unauthorizedAdmin = Admin::create([
            'name' => 'Restricted Admin',
            'email' => 'restricted@masalamart.com',
            'password' => Hash::make('password'),
        ]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($unauthorizedAdmin, 'admin')
            ->patch(route('admin.orders.status.update', $order), [
                'status' => 'packing',
            ]);

        $response->assertStatus(403);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_admin_without_manage_orders_permission_cannot_update_items_packed(): void
    {
        $unauthorizedAdmin = Admin::create([
            'name' => 'Restricted Admin',
            'email' => 'restricted@masalamart.com',
            'password' => Hash::make('password'),
        ]);

        $order = Order::factory()->create(['status' => 'packing']);

        $response = $this->actingAs($unauthorizedAdmin, 'admin')
            ->patch(route('admin.orders.items.packed', $order), [
                'is_packed' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_without_manage_orders_permission_cannot_refund_order_item(): void
    {
        $unauthorizedAdmin = Admin::create([
            'name' => 'Restricted Admin',
            'email' => 'restricted@masalamart.com',
            'password' => Hash::make('password'),
        ]);

        $product = Product::factory()->create(['price' => 10]);
        $order = Order::factory()->create(['status' => 'packing', 'total' => 20]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 10,
            'quantity' => 2,
            'total_price' => 20,
        ]);

        $response = $this->actingAs($unauthorizedAdmin, 'admin')
            ->post(route('admin.orders.items.refund', [$order, $item]), [
                'quantity' => 1,
                'reason' => 'Damaged during handling',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_with_manage_orders_permission_can_access_orders_index(): void
    {
        $orderHandler = Admin::create([
            'name' => 'Order Handler Staff',
            'email' => 'handler@masalamart.com',
            'password' => Hash::make('password'),
        ]);
        $orderHandlerRole = Role::where('name', 'Order Handler')->where('guard_name', 'admin')->first();
        $orderHandler->assignRole($orderHandlerRole);

        $response = $this->actingAs($orderHandler, 'admin')
            ->get(route('admin.orders.index'));

        $response->assertStatus(200);
    }

    public function test_admin_with_manage_orders_permission_can_update_order_status(): void
    {
        $orderHandler = Admin::create([
            'name' => 'Order Handler Staff',
            'email' => 'handler@masalamart.com',
            'password' => Hash::make('password'),
        ]);
        $orderHandlerRole = Role::where('name', 'Order Handler')->where('guard_name', 'admin')->first();
        $orderHandler->assignRole($orderHandlerRole);

        $order = Order::factory()->create([
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($orderHandler, 'admin')
            ->patch(route('admin.orders.status.update', $order), [
                'status' => 'packing',
            ]);

        $response->assertRedirect();
        $this->assertSame('packing', $order->fresh()->status);
    }
}
