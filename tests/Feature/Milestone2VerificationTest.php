<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderController;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Milestone2VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    /**
     * Test that an unauthenticated visitor is redirected to admin login.
     */
    public function test_unauthenticated_visitor_cannot_access_settings_routes(): void
    {
        $this->get('/admin/store-info')->assertRedirect(route('admin.login'));
        $this->put('/admin/store-info', [])->assertRedirect(route('admin.login'));
        $this->post('/admin/store-info/busy-mode', [])->assertRedirect(route('admin.login'));
        $this->get('/admin/pickup-settings')->assertRedirect(route('admin.login'));
        $this->put('/admin/pickup-settings', [])->assertRedirect(route('admin.login'));
    }

    /**
     * Test that an admin with only "manage products" receives 403 Forbidden.
     */
    public function test_admin_with_only_manage_products_receives_403_on_settings_routes(): void
    {
        $productAdmin = Admin::create([
            'name' => 'Catalog Staff',
            'email' => 'catalog@masalamart.com',
            'password' => Hash::make('password'),
        ]);

        $productRole = Role::firstOrCreate(['name' => 'Catalog Specialist', 'guard_name' => 'admin']);
        $productRole->syncPermissions(['manage products']);
        $productAdmin->assignRole($productRole);

        // GET /admin/store-info
        $this->actingAs($productAdmin, 'admin')
            ->get('/admin/store-info')
            ->assertStatus(403);

        // PUT /admin/store-info
        $this->actingAs($productAdmin, 'admin')
            ->put('/admin/store-info', ['name' => 'Hacked Store'])
            ->assertStatus(403);

        // POST /admin/store-info/busy-mode
        $this->actingAs($productAdmin, 'admin')
            ->post('/admin/store-info/busy-mode', ['busy_mode_extra_minutes' => 15])
            ->assertStatus(403);

        // GET /admin/pickup-settings
        $this->actingAs($productAdmin, 'admin')
            ->get('/admin/pickup-settings')
            ->assertStatus(403);

        // PUT /admin/pickup-settings
        $this->actingAs($productAdmin, 'admin')
            ->put('/admin/pickup-settings', ['prep_time_minutes' => 30])
            ->assertStatus(403);
    }

    /**
     * Test that an admin with "manage settings" permission succeeds (200 OK).
     */
    public function test_admin_with_manage_settings_can_access_settings_routes(): void
    {
        $settingsAdmin = Admin::create([
            'name' => 'Settings Specialist',
            'email' => 'settings@masalamart.com',
            'password' => Hash::make('password'),
        ]);

        $settingsRole = Role::firstOrCreate(['name' => 'Settings Admin Role', 'guard_name' => 'admin']);
        $settingsRole->syncPermissions(['manage settings']);
        $settingsAdmin->assignRole($settingsRole);

        $this->actingAs($settingsAdmin, 'admin')
            ->get('/admin/store-info')
            ->assertStatus(200);

        $this->actingAs($settingsAdmin, 'admin')
            ->get('/admin/pickup-settings')
            ->assertStatus(200);
    }

    /**
     * Test that Store Manager and Super Admin seeded roles have access to settings routes.
     */
    public function test_seeded_store_manager_and_super_admin_can_access_settings_routes(): void
    {
        $storeManager = Admin::where('email', 'manager@masalamart.com')->first();
        $this->assertNotNull($storeManager);

        $this->actingAs($storeManager, 'admin')
            ->get('/admin/store-info')
            ->assertStatus(200);

        $this->actingAs($storeManager, 'admin')
            ->get('/admin/pickup-settings')
            ->assertStatus(200);

        $superAdmin = Admin::where('email', 'admin@masalamart.com')->first();
        $this->assertNotNull($superAdmin);

        $this->actingAs($superAdmin, 'admin')
            ->get('/admin/store-info')
            ->assertStatus(200);

        $this->actingAs($superAdmin, 'admin')
            ->get('/admin/pickup-settings')
            ->assertStatus(200);
    }

    /**
     * Test that OrderController::validateOrderRequirements scales with bounded queries O(1).
     */
    public function test_validate_order_requirements_scales_with_bounded_queries(): void
    {
        $controller = new class extends OrderController
        {
            public function invokeValidation(array $validated, StoreSetting $storeInfo): array
            {
                return $this->validateOrderRequirements($validated, $storeInfo);
            }
        };

        $storeInfo = StoreSetting::current();
        $storeInfo->update([
            'pickup_slot_start_time' => '00:00',
            'pickup_slot_end_time' => '23:59',
            'is_pickup_active' => true,
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);

        $products = [];
        for ($i = 1; $i <= 15; $i++) {
            $products[] = Product::factory()->create([
                'stock' => 100,
                'price' => 10.00,
            ]);
        }

        $kit = RecipeKit::create([
            'name' => 'Empirical Test Kit',
            'slug' => 'empirical-test-kit',
            'price' => 25.00,
            'is_active' => true,
        ]);
        $kit->products()->attach($products[0]->id, ['quantity' => 2]);

        $counts = [];

        // 1. Single product item
        DB::flushQueryLog();
        DB::enableQueryLog();
        $items1 = [
            ['id' => $products[0]->id, 'name' => $products[0]->name, 'price' => 10.00, 'quantity' => 1, 'type' => 'product'],
        ];
        $controller->invokeValidation(['items' => $items1, 'pickup_timing_mode' => 'asap'], $storeInfo);
        $counts['1_item'] = count(DB::getQueryLog());

        // 2. 5 product items
        DB::flushQueryLog();
        DB::enableQueryLog();
        $items5 = [];
        for ($i = 0; $i < 5; $i++) {
            $items5[] = ['id' => $products[$i]->id, 'name' => $products[$i]->name, 'price' => 10.00, 'quantity' => 1, 'type' => 'product'];
        }
        $controller->invokeValidation(['items' => $items5, 'pickup_timing_mode' => 'asap'], $storeInfo);
        $counts['5_items'] = count(DB::getQueryLog());

        // 3. 15 product items
        DB::flushQueryLog();
        DB::enableQueryLog();
        $items15 = [];
        for ($i = 0; $i < 15; $i++) {
            $items15[] = ['id' => $products[$i]->id, 'name' => $products[$i]->name, 'price' => 10.00, 'quantity' => 1, 'type' => 'product'];
        }
        $controller->invokeValidation(['items' => $items15, 'pickup_timing_mode' => 'asap'], $storeInfo);
        $counts['15_items'] = count(DB::getQueryLog());

        // 4. Mixed: 10 products + 5 recipe kits
        DB::flushQueryLog();
        DB::enableQueryLog();
        $itemsMixed = [];
        for ($i = 0; $i < 10; $i++) {
            $itemsMixed[] = ['id' => $products[$i]->id, 'name' => $products[$i]->name, 'price' => 10.00, 'quantity' => 1, 'type' => 'product'];
        }
        for ($i = 0; $i < 5; $i++) {
            $itemsMixed[] = ['id' => 'kit-'.$kit->id, 'name' => $kit->name, 'price' => 25.00, 'quantity' => 1, 'type' => 'recipe-kit'];
        }
        $controller->invokeValidation(['items' => $itemsMixed, 'pickup_timing_mode' => 'asap'], $storeInfo);
        $counts['mixed_15_items'] = count(DB::getQueryLog());

        // Query bounding assertions:
        // 1 item, 5 items, and 15 items of products must execute the exact same number of queries (O(1))
        $this->assertEquals($counts['1_item'], $counts['5_items'], '5 items should execute same query count as 1 item');
        $this->assertEquals($counts['1_item'], $counts['15_items'], '15 items should execute same query count as 1 item');
        $this->assertLessThanOrEqual(4, $counts['mixed_15_items'], 'Mixed 15 items should execute <= 4 bounded queries');
    }

    /**
     * Test that supplier code generation handles existing high numbers and collisions cleanly.
     */
    public function test_supplier_unique_code_generation_handles_gaps_and_high_numbers(): void
    {
        // Pre-create supplier with code SUP-050
        $existing = Supplier::create([
            'name' => 'High Num Supplier',
            'code' => 'SUP-050',
        ]);

        $this->assertEquals('SUP-050', $existing->code);

        // Next auto-generated supplier should jump past SUP-050 to SUP-051
        $auto1 = Supplier::create([
            'name' => 'Auto Supplier 1',
        ]);

        $this->assertEquals('SUP-051', $auto1->code);

        // Create another manual supplier SUP-052
        Supplier::create([
            'name' => 'Manual Supplier 52',
            'code' => 'SUP-052',
        ]);

        // Next auto-generated supplier should jump past SUP-052 to SUP-053
        $auto2 = Supplier::create([
            'name' => 'Auto Supplier 2',
        ]);

        $this->assertEquals('SUP-053', $auto2->code);
    }

    /**
     * Test that customer recent orders does not starve customers when one has many orders.
     */
    public function test_customer_recent_orders_prevents_starvation(): void
    {
        $superAdmin = Admin::where('email', 'admin@masalamart.com')->first();

        // Customer A has 25 orders
        $userA = User::factory()->create(['name' => 'Frequent Buyer A']);
        for ($i = 1; $i <= 25; $i++) {
            Order::factory()->create([
                'user_id' => $userA->id,
                'customer_name' => $userA->name,
                'customer_email' => $userA->email,
                'order_number' => 'ORD-A-'.$i,
            ]);
        }

        // Customer B has 3 orders
        $userB = User::factory()->create(['name' => 'Normal Buyer B']);
        for ($i = 1; $i <= 3; $i++) {
            Order::factory()->create([
                'user_id' => $userB->id,
                'customer_name' => $userB->name,
                'customer_email' => $userB->email,
                'order_number' => 'ORD-B-'.$i,
            ]);
        }

        // Customer C has 1 order
        $userC = User::factory()->create(['name' => 'Occasional Buyer C']);
        Order::factory()->create([
            'user_id' => $userC->id,
            'customer_name' => $userC->name,
            'customer_email' => $userC->email,
            'order_number' => 'ORD-C-1',
        ]);

        $response = $this->actingAs($superAdmin, 'admin')->get(route('admin.customers.index'));
        $response->assertStatus(200);

        // Check Inertia customers
        $customers = collect($response->original->getData()['page']['props']['customers']['data']);

        $custB = $customers->firstWhere('id', $userB->id);
        $custC = $customers->firstWhere('id', $userC->id);

        $this->assertNotNull($custB, 'Customer B should be in listing');
        $this->assertNotNull($custC, 'Customer C should be in listing');

        // Verify Customer B has 3 recent orders (not starved!)
        $this->assertCount(3, $custB['recent_orders']);
        // Verify Customer C has 1 recent order (not starved!)
        $this->assertCount(1, $custC['recent_orders']);
    }
}
