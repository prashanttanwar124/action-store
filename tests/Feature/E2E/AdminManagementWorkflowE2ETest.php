<?php

namespace Tests\Feature\E2E;

use App\Events\OrderItemsPackedUpdated;
use App\Events\OrderStatusUpdated;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManagementWorkflowE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
    }

    public function test_admin_authentication_and_dashboard_metrics(): void
    {
        // Guest visits admin login
        $loginPage = $this->get(route('admin.login'));
        $loginPage->assertOk();

        // Successful authentication
        $loginResponse = $this->post(route('admin.login'), [
            'email' => 'admin@masalamart.com',
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin, 'admin');

        // Admin dashboard metrics
        $dashboardResponse = $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('stats.total_products')
            ->has('stats.total_users')
            ->has('stats.total_admins')
            ->has('stats.total_roles')
            ->has('stats.total_permissions')
            ->has('admins')
            ->has('roles')
        );

        // Admin logout
        $logoutResponse = $this->actingAs($this->admin, 'admin')->post(route('admin.logout'));
        $logoutResponse->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_admin_kds_orders_listing_search_and_filtering(): void
    {
        $order1 = Order::factory()->create([
            'order_number' => '#MM-20261021-1001-A1B2',
            'customer_name' => 'Vikram Seth',
            'customer_email' => 'vikram@example.com',
            'status' => 'confirmed',
            'total' => 35.00,
        ]);

        $order2 = Order::factory()->create([
            'order_number' => '#MM-20261021-1002-C3D4',
            'customer_name' => 'Kavita Roy',
            'customer_email' => 'kavita@example.com',
            'status' => 'packing',
            'total' => 52.00,
        ]);

        // Access KDS index
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.index'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 2)
            ->has('stats.total_all')
            ->has('stats.confirmed')
            ->has('stats.packing')
        );

        // Filter by packing status
        $filterResponse = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.index', ['status' => 'packing']));
        $filterResponse->assertOk();
        $filterResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', '#MM-20261021-1002-C3D4')
        );

        // Search by customer name
        $searchResponse = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.index', ['search' => 'Vikram']));
        $searchResponse->assertOk();
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.customer_name', 'Vikram Seth')
        );
    }

    public function test_admin_kds_order_status_progression_lifecycle(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = Order::factory()->create([
            'order_number' => '#MM-90001',
            'status' => 'confirmed',
        ]);

        // confirmed -> packing
        $res1 = $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), ['status' => 'packing']);
        $res1->assertOk()->assertJsonPath('order.status', 'packing');
        $this->assertEquals('packing', $order->fresh()->status);

        // packing -> ready_for_pickup
        $res2 = $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), ['status' => 'ready_for_pickup']);
        $res2->assertOk()->assertJsonPath('order.status', 'ready_for_pickup');
        $this->assertEquals('ready_for_pickup', $order->fresh()->status);

        // ready_for_pickup -> completed
        $res3 = $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), ['status' => 'completed']);
        $res3->assertOk()->assertJsonPath('order.status', 'completed');
        $this->assertEquals('completed', $order->fresh()->status);

        Event::assertDispatched(OrderStatusUpdated::class, 3);
    }

    public function test_admin_kds_item_packing_tracking(): void
    {
        Event::fake([OrderItemsPackedUpdated::class]);

        $order = Order::factory()->create(['status' => 'packing']);
        $item1 = $order->items()->create([
            'name' => 'Kashmiri Chilli Powder',
            'unit_price' => 4.50,
            'quantity' => 2,
            'total_price' => 9.00,
            'is_packed' => false,
        ]);
        $item2 = $order->items()->create([
            'name' => 'Basmati Rice',
            'unit_price' => 12.00,
            'quantity' => 1,
            'total_price' => 12.00,
            'is_packed' => false,
        ]);

        // Pack item 1 individually
        $res1 = $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.items.packed', $order->id), [
                'item_id' => $item1->id,
                'is_packed' => true,
            ]);

        $res1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('packed_item_ids', [$item1->id]);

        $this->assertTrue($item1->fresh()->is_packed);
        $this->assertFalse($item2->fresh()->is_packed);

        // Bulk pack remaining items
        $res2 = $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.items.packed', $order->id), [
                'is_packed' => true,
            ]);

        $res2->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($item1->fresh()->is_packed);
        $this->assertTrue($item2->fresh()->is_packed);

        Event::assertDispatched(OrderItemsPackedUpdated::class, 2);
    }

    public function test_admin_kds_partial_item_refund_zeros_product_stock_and_updates_order(): void
    {
        Event::fake([OrderItemsPackedUpdated::class, OrderStatusUpdated::class]);

        $oosProduct = Product::factory()->create(['name' => 'Haldiram Bhujia', 'price' => 4.50, 'stock' => 5]);
        $goodProduct = Product::factory()->create(['name' => 'Amul Milk', 'price' => 3.50, 'stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 12.50,
            'total' => 12.50,
            'reserved_stock' => [
                $oosProduct->id => 2,
                $goodProduct->id => 1,
            ],
        ]);

        $item1 = $order->items()->create([
            'product_id' => $oosProduct->id,
            'name' => $oosProduct->name,
            'unit_price' => 4.50,
            'quantity' => 2,
            'total_price' => 9.00,
            'is_packed' => true,
        ]);

        $item2 = $order->items()->create([
            'product_id' => $goodProduct->id,
            'name' => $goodProduct->name,
            'unit_price' => 3.50,
            'quantity' => 1,
            'total_price' => 3.50,
            'is_packed' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $item1->id]), [
                'quantity' => 2,
                'zero_product_stock' => true,
                'reason' => 'Damaged during packing',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', 'packing')
            ->assertJsonPath('order.refunded_total', 9);

        // Product inventory on storefront shelf zeroed
        $this->assertEquals(0, $oosProduct->fresh()->stock);
        $this->assertEquals(10, $goodProduct->fresh()->stock);

        // Order updated with refund note and refunded_total
        $freshOrder = $order->fresh();
        $this->assertEquals(9.00, (float) $freshOrder->refunded_total);
        $this->assertStringContainsString('Damaged during packing', (string) $freshOrder->notes);
        $this->assertFalse($item1->fresh()->is_packed);
    }

    public function test_admin_full_order_cancellation_restocks_inventory(): void
    {
        $product = Product::factory()->create(['name' => 'Paneer Tins', 'price' => 15.00, 'stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'subtotal' => 30.00,
            'total' => 30.00,
            'reserved_stock' => [
                $product->id => 2,
            ],
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 15.00,
            'quantity' => 2,
            'total_price' => 30.00,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.status.update', $order->id), [
                'status' => 'cancelled',
            ]);

        $response->assertOk();
        $freshOrder = $order->fresh();
        $this->assertEquals('cancelled', $freshOrder->status);
        $this->assertEquals(30.00, (float) $freshOrder->refunded_total);

        // Stock restored from 10 to 12
        $this->assertEquals(12, $product->fresh()->stock);
    }

    public function test_admin_product_catalog_crud_lifecycle(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('cumin_seeds.jpg', 400, 400);

        // Create product
        $createResponse = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Organic Whole Cumin Seeds',
            'slug' => 'organic-whole-cumin-seeds',
            'category' => 'spices',
            'category_title' => 'Spices & Masalas',
            'price' => 6.99,
            'original_price' => 8.49,
            'stock' => 50,
            'size_main' => '200 g',
            'image_files' => [$file],
        ]);

        $createResponse->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'Organic Whole Cumin Seeds',
            'slug' => 'organic-whole-cumin-seeds',
            'price' => 6.99,
        ]);

        $product = Product::where('slug', 'organic-whole-cumin-seeds')->firstOrFail();

        // Update product
        $updateResponse = $this->actingAs($this->admin, 'admin')->post(route('admin.products.update', $product->id), [
            'name' => 'Organic Whole Cumin Seeds — Premium',
            'slug' => 'organic-whole-cumin-seeds',
            'category' => 'spices',
            'category_title' => 'Spices & Masalas',
            'price' => 7.49,
            'stock' => 45,
            'size_main' => '200 g',
        ]);

        $updateResponse->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Organic Whole Cumin Seeds — Premium',
            'price' => 7.49,
            'stock' => 45,
        ]);

        // Delete product
        $deleteResponse = $this->actingAs($this->admin, 'admin')->delete(route('admin.products.destroy', $product->id));
        $deleteResponse->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_category_management_crud_and_toggle(): void
    {
        // Create Category
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), [
            'name' => 'Ayurvedic Teas & Herbs',
            'slug' => 'ayurvedic-teas-herbs',
            'hindi_title' => 'हर्बल चाय',
            'description' => 'Herbal wellness teas and loose dried leaves.',
            'icon' => 'Sparkles',
            'sort_order' => 15,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'slug' => 'ayurvedic-teas-herbs',
            'name' => 'Ayurvedic Teas & Herbs',
            'is_active' => true,
        ]);

        $cat = Category::where('slug', 'ayurvedic-teas-herbs')->firstOrFail();

        // Update Category
        $updateResponse = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.update', $cat->id), [
            'name' => 'Ayurvedic Teas & Herbal Infusions',
            'slug' => 'ayurvedic-teas-herbs',
            'description' => 'Updated organic herbal wellness collection.',
            'sort_order' => 16,
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'id' => $cat->id,
            'name' => 'Ayurvedic Teas & Herbal Infusions',
        ]);

        // Toggle Category Active Status
        $toggleResponse = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.toggle', $cat->id));
        $toggleResponse->assertRedirect();
        $this->assertFalse((bool) $cat->fresh()->is_active);
    }

    public function test_admin_recipe_kit_management_with_group_buy_products(): void
    {
        Storage::fake('public');
        $img = UploadedFile::fake()->image('rajma_chawal_kit.jpg', 500, 500);

        $product1 = Product::factory()->create(['name' => 'Chitra Rajma Beans', 'price' => 4.99]);
        $product2 = Product::factory()->create(['name' => 'Dehraduni Basmati Rice', 'price' => 7.99]);

        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.recipe-kits.store'), [
            'name' => 'Authentic Rajma Chawal Kit',
            'slug' => 'authentic-rajma-chawal-kit',
            'subtitle_tag' => 'PUNJABI HOMESTYLE FAVORITE',
            'description' => 'Everything you need to make classic comfort food at home.',
            'servings' => 'Serves 4–6',
            'cooking_time' => '45 mins',
            'difficulty' => 'Easy',
            'price' => 10.99,
            'original_price' => 14.50,
            'is_active' => true,
            'image_files' => [$img],
            'products' => [
                ['product_id' => $product1->id, 'quantity' => 1, 'unit_notes' => '1 kg bag'],
                ['product_id' => $product2->id, 'quantity' => 1, 'unit_notes' => '2 kg bag'],
            ],
        ]);

        $response->assertRedirect(route('admin.recipe-kits.index'));
        $this->assertDatabaseHas('recipe_kits', [
            'slug' => 'authentic-rajma-chawal-kit',
            'price' => 10.99,
        ]);

        $kit = RecipeKit::where('slug', 'authentic-rajma-chawal-kit')->firstOrFail();
        $this->assertCount(2, $kit->products);
    }

    public function test_admin_store_settings_and_busy_mode_management(): void
    {
        // Update Store Info
        $response = $this->actingAs($this->admin, 'admin')->put(route('admin.store-info.update'), [
            'name' => 'Masala Mart Flagship Store',
            'tagline' => 'Finest South Asian Groceries',
            'address' => '100 King St West',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip' => 'M5H 1A1',
            'phone' => '+1 (416) 555-0100',
            'email' => 'toronto@masalamart.com',
            'opening_hours' => 'Mon–Sat: 9:00 AM – 9:00 PM',
            'pickup_time' => 'Ready in 20 mins',
            'maps_url' => 'https://maps.google.com/?q=Toronto',
            'is_pickup_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('store_settings', [
            'name' => 'Masala Mart Flagship Store',
            'city' => 'Toronto',
        ]);

        // Quick busy-mode toggle
        $busyResponse = $this->actingAs($this->admin, 'admin')->post(route('admin.store-info.busy-mode'), [
            'busy_mode_extra_minutes' => 20,
            'busy_mode_reason' => 'Peak holiday shopping rush',
        ]);

        $busyResponse->assertRedirect();
        $this->assertDatabaseHas('store_settings', [
            'busy_mode_extra_minutes' => 20,
            'busy_mode_reason' => 'Peak holiday shopping rush',
        ]);

        $setting = StoreSetting::current();
        $this->assertTrue($setting->is_busy);
    }
}
