<?php

namespace Tests\Feature\Adversarial;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\StripePayments;
use Carbon\Carbon;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Challenger M5-2 Dedicated Adversarial Test Suite:
 * White-box adversarial probing of Admin Management, Security & RBAC,
 * Mass Assignment, URL Scheme XSS, Slug Contention, Database Atomicity,
 * and Real-Time KDS Metrics.
 */
class AdminAndDatabaseAdversarialTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected Admin $storeManager;

    protected Admin $orderHandler;

    protected Admin $unprivilegedAdmin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);

        $this->superAdmin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $this->storeManager = Admin::where('email', 'manager@masalamart.com')->firstOrFail();

        // Create Order Handler admin
        $this->orderHandler = Admin::firstOrCreate(
            ['email' => 'handler@masalamart.com'],
            ['name' => 'KDS Handler', 'password' => Hash::make('password')]
        );
        $this->orderHandler->assignRole('Order Handler');

        // Create completely unprivileged admin (no role, no permissions)
        $this->unprivilegedAdmin = Admin::create([
            'name' => 'Unprivileged Rogue Admin',
            'email' => 'unprivileged@masalamart.com',
            'password' => Hash::make('password'),
        ]);

        // Create standard customer
        $this->customer = User::factory()->create([
            'name' => 'Adversarial Customer',
            'email' => 'adversary@customer.com',
        ]);
    }

    // =========================================================================
    // SECTION 1: PERMISSION BOUNDARY ENFORCEMENT & RBAC NEGATIVE PRIVILEGE PROBES
    // =========================================================================

    /**
     * Unauthenticated guest users are strictly rejected from all state-changing admin routes.
     */
    public function test_unauthenticated_guests_strictly_rejected_from_admin_state_mutations(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);
        $product = Product::factory()->create(['stock' => 10]);
        $category = Category::create(['name' => 'Lentils', 'slug' => 'lentils']);

        // Orders status update
        $this->patchJson(route('admin.orders.status.update', $order), ['status' => 'packing'])
            ->assertStatus(401);

        // Product creation
        $this->postJson(route('admin.products.store'), [
            'name' => 'Rogue Product',
            'category' => 'lentils',
            'price' => 9.99,
        ])->assertStatus(401);

        // Category deletion
        $this->deleteJson(route('admin.categories.destroy', $category))
            ->assertStatus(401);

        // Settings update
        $this->putJson(route('admin.store-info.update'), ['name' => 'Hacked Store'])
            ->assertStatus(401);

        // Roles store
        $this->postJson(route('admin.roles.store'), ['name' => 'Rogue Role'])
            ->assertStatus(401);

        // Customer account creation
        $this->postJson(route('admin.customers.store'), [
            'name' => 'Rogue User',
            'email' => 'rogue@user.com',
            'password' => 'Password123!',
        ])->assertStatus(401);
    }

    /**
     * Authenticated customer (web guard) cannot access any admin routes.
     */
    public function test_customer_guard_cannot_access_or_mutate_admin_resources(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);

        // Attempting admin dashboard
        $this->actingAs($this->customer, 'web')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        // Attempting to cancel an order via admin route
        $this->actingAs($this->customer, 'web')
            ->patchJson(route('admin.orders.status.update', $order), ['status' => 'cancelled'])
            ->assertStatus(401);

        // Attempting to create an admin role
        $this->actingAs($this->customer, 'web')
            ->postJson(route('admin.roles.store'), ['name' => 'Privilege Escalation'])
            ->assertStatus(401);
    }

    /**
     * An admin with NO assigned permissions receives 403 Forbidden across all protected groups.
     */
    public function test_unprivileged_admin_receives_403_across_all_admin_feature_groups(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);
        $product = Product::factory()->create(['stock' => 10]);
        $category = Category::create(['name' => 'Adversarial Category', 'slug' => 'adversarial-category']);

        $acting = $this->actingAs($this->unprivilegedAdmin, 'admin');

        // 1. Orders group (requires 'manage orders')
        $acting->get(route('admin.orders.index'))->assertStatus(403);
        $acting->patchJson(route('admin.orders.status.update', $order), ['status' => 'packing'])->assertStatus(403);

        // 2. Products & Catalog group (requires 'manage products')
        $acting->get(route('admin.products.index'))->assertStatus(403);
        $acting->postJson(route('admin.products.store'), ['name' => 'New Prod', 'category' => 'spices', 'price' => 5])->assertStatus(403);
        $acting->delete(route('admin.categories.destroy', $category))->assertStatus(403);

        // 3. Settings group (requires 'manage settings')
        $acting->get(route('admin.store-info.edit'))->assertStatus(403);
        $acting->post(route('admin.store-info.busy-mode'), ['busy_mode_extra_minutes' => 30])->assertStatus(403);

        // 4. Customer management group (requires 'manage users')
        $acting->get(route('admin.customers.index'))->assertStatus(403);
        $acting->delete(route('admin.customers.destroy', $this->customer))->assertStatus(403);

        // 5. Roles group (requires 'manage roles')
        $acting->postJson(route('admin.roles.store'), ['name' => 'Escalated Role'])->assertStatus(403);
    }

    /**
     * Cross-permission isolation: An Order Handler (manage orders ONLY) cannot tamper with catalog, settings, or roles.
     */
    public function test_order_handler_isolated_strictly_to_orders_group(): void
    {
        $product = Product::factory()->create(['stock' => 20]);
        $acting = $this->actingAs($this->orderHandler, 'admin');

        // Order operations permitted
        $order = Order::factory()->create(['status' => 'confirmed']);
        $acting->patchJson(route('admin.orders.status.update', $order), ['status' => 'packing'])
            ->assertOk();

        // Catalog operations blocked with 403
        $acting->get(route('admin.products.index'))->assertStatus(403);
        $acting->postJson(route('admin.products.store'), [
            'name' => 'Unauthorized Product',
            'category' => 'spices',
            'price' => 9.99,
        ])->assertStatus(403);
        $acting->delete(route('admin.products.destroy', $product))->assertStatus(403);

        // Settings operations blocked with 403
        $acting->get(route('admin.store-info.edit'))->assertStatus(403);
        $acting->post(route('admin.store-info.busy-mode'), ['busy_mode_extra_minutes' => 15])->assertStatus(403);

        // Customer management blocked with 403
        $acting->get(route('admin.customers.index'))->assertStatus(403);

        // Role management blocked with 403
        $acting->postJson(route('admin.roles.store'), ['name' => 'New Role'])->assertStatus(403);
    }

    /**
     * Store Manager has all permissions EXCEPT 'manage roles', and cannot create roles or sync admin roles.
     */
    public function test_store_manager_cannot_escalate_privileges_or_manage_roles(): void
    {
        $acting = $this->actingAs($this->storeManager, 'admin');

        // Store manager can manage products and orders
        $acting->get(route('admin.orders.index'))->assertOk();
        $acting->get(route('admin.products.index'))->assertOk();

        // Store manager CANNOT create roles (requires 'manage roles')
        $acting->postJson(route('admin.roles.store'), [
            'name' => 'Elevated Role',
        ])->assertStatus(403);

        // Store manager CANNOT assign roles to admins
        $acting->postJson(route('admin.admins.roles.sync', $this->unprivilegedAdmin), [
            'roles' => ['Super Admin'],
        ])->assertStatus(403);
    }

    // =========================================================================
    // SECTION 2: STORED XSS / MALICIOUS URL SCHEME HARDENING
    // =========================================================================

    /**
     * Slider link_url strictly rejects diverse XSS vectors and obscure pseudo-protocols.
     */
    public function test_slider_link_url_strictly_rejects_obscure_and_cased_xss_schemes(): void
    {
        $vectors = [
            'javascript:alert(1)',
            'JavaScript:alert(document.cookie)',
            'JAVASCRIPT:void(0)',
            '  javascript:alert(1)',
            'vbscript:msgbox("xss")',
            'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'data:text/javascript,alert(1)',
            'blob:https://store.masalamart.com/1234',
            'file:///etc/passwd',
            'about:blank',
        ];

        foreach ($vectors as $vector) {
            $response = $this->actingAs($this->superAdmin, 'admin')->postJson(route('admin.sliders.store'), [
                'title' => 'Adversarial Slide '.md5($vector),
                'cta_text' => 'Click Here',
                'link_url' => $vector,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['link_url']);
        }
    }

    /**
     * Slider link_url permits valid relative and https/http absolute URLs.
     */
    public function test_slider_link_url_permits_safe_schemes(): void
    {
        $safeUrls = [
            '/products/organic-toor-dal',
            '/recipe-kits/paneer-tikka',
            'https://masalamart.com/summer-specials',
            'http://masalamart.com/offers',
        ];

        foreach ($safeUrls as $url) {
            $title = 'Safe Slide '.md5($url);
            $response = $this->actingAs($this->superAdmin, 'admin')->postJson(route('admin.sliders.store'), [
                'title' => $title,
                'cta_text' => 'Explore',
                'link_url' => $url,
            ]);

            $response->assertRedirect(route('admin.sliders.index'));
            $this->assertDatabaseHas('sliders', ['title' => $title, 'link_url' => $url]);
        }
    }

    /**
     * StoreSetting maps_url rejects script pseudo-protocols while allowing valid map providers and paths.
     */
    public function test_store_setting_maps_url_rejects_malicious_schemes_and_allows_safe(): void
    {
        $current = StoreSetting::current();

        // 1. Malicious attempt
        $badResponse = $this->actingAs($this->superAdmin, 'admin')->putJson(
            route('admin.store-info.update'),
            array_merge($current->toArray(), [
                'maps_url' => 'javascript:alert(document.domain)',
            ])
        );
        $badResponse->assertStatus(422)->assertJsonValidationErrors(['maps_url']);

        // 2. Safe relative and absolute attempts
        $safeResponse = $this->actingAs($this->superAdmin, 'admin')->putJson(
            route('admin.store-info.update'),
            array_merge($current->toArray(), [
                'maps_url' => 'https://maps.google.com/?q=Masala+Mart',
            ])
        );
        $safeResponse->assertRedirect();
        $this->assertSame('https://maps.google.com/?q=Masala+Mart', StoreSetting::current()->maps_url);
    }

    // =========================================================================
    // SECTION 3: MASS ASSIGNMENT VULNERABILITY PROBES
    // =========================================================================

    /**
     * User model strictly prevents mass assignment of reward_points on creation and update.
     */
    public function test_user_model_strictly_guards_reward_points_against_mass_assignment(): void
    {
        // 1. Direct Eloquent create with injected reward_points
        $user = User::create([
            'name' => 'Mass Assign Probe',
            'email' => 'massassign@probe.com',
            'password' => Hash::make('password'),
            'reward_points' => 999999,
        ]);

        // Default balance should be 100 (welcome bonus), NOT 999999
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // 2. Direct Eloquent update with injected reward_points
        $user->update([
            'reward_points' => 500000,
            'name' => 'Updated Name',
        ]);

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    /**
     * AdminCustomerController prevents tampering with customer reward points or arbitrary attributes.
     */
    public function test_admin_customer_controller_ignores_injected_reward_points(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')->post(route('admin.customers.store'), [
            'name' => 'Injected Points Customer',
            'email' => 'injected_points@example.com',
            'password' => 'SecurePass123!',
            'reward_points' => 888888,
        ]);
        $response->assertRedirect();

        $createdUser = User::where('email', 'injected_points@example.com')->firstOrFail();
        $this->assertSame(100, (int) $createdUser->reward_points);

        // Update customer attempt with points injection
        $updateResp = $this->actingAs($this->superAdmin, 'admin')->patch(route('admin.customers.update', $createdUser), [
            'name' => 'Injected Points Updated',
            'email' => 'injected_points@example.com',
            'reward_points' => 777777,
        ]);
        $updateResp->assertRedirect();

        $this->assertSame('Injected Points Updated', $createdUser->fresh()->name);
        $this->assertSame(100, (int) $createdUser->fresh()->reward_points);
    }

    // =========================================================================
    // SECTION 4: SLUG COLLISION DISAMBIGUATION UNDER HIGH CONTENTION
    // =========================================================================

    /**
     * High contention stress test: 25 sequential product creations with the identical name
     * automatically disambiguate into unique slugs without 500 QueryException.
     */
    public function test_product_slug_generation_under_high_collision_stress(): void
    {
        $commonName = 'Adversarial Basmati Rice';
        $createdSlugs = [];

        for ($i = 0; $i < 25; $i++) {
            $resp = $this->actingAs($this->superAdmin, 'admin')->post(route('admin.products.store'), [
                'name' => $commonName,
                'slug' => '',
                'category' => 'grocery',
                'price' => 12.50 + $i,
            ]);
            $resp->assertRedirect(route('admin.products.index'));
        }

        $allProducts = Product::where('name', $commonName)->get();
        $this->assertCount(25, $allProducts);

        $slugs = $allProducts->pluck('slug')->all();
        // All 25 slugs must be strictly unique
        $this->assertCount(25, array_unique($slugs));

        // First slug should be the clean base slug
        $this->assertContains('adversarial-basmati-rice', $slugs);
        // Sequential suffixes should exist
        $this->assertContains('adversarial-basmati-rice-1', $slugs);
        $this->assertContains('adversarial-basmati-rice-24', $slugs);
    }

    /**
     * Non-alphanumeric and emoji-only product and category names fall back to default entities
     * and disambiguate cleanly under repeated collisions.
     */
    public function test_non_alphanumeric_emoji_names_fallback_and_disambiguate_cleanly(): void
    {
        // 5 products with only emojis
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->superAdmin, 'admin')->post(route('admin.products.store'), [
                'name' => '🔥✨🛒',
                'slug' => '',
                'category' => 'spices',
                'price' => 3.99,
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('products', ['slug' => 'product']);
        $this->assertDatabaseHas('products', ['slug' => 'product-1']);
        $this->assertDatabaseHas('products', ['slug' => 'product-2']);
        $this->assertDatabaseHas('products', ['slug' => 'product-3']);
        $this->assertDatabaseHas('products', ['slug' => 'product-4']);

        // 4 categories with only emojis
        for ($j = 0; $j < 4; $j++) {
            $this->actingAs($this->superAdmin, 'admin')->post(route('admin.categories.store'), [
                'name' => '🎉✨🔥',
                'slug' => '',
            ])->assertRedirect();
        }

        $this->assertDatabaseHas('categories', ['slug' => 'category']);
        $this->assertDatabaseHas('categories', ['slug' => 'category-1']);
        $this->assertDatabaseHas('categories', ['slug' => 'category-2']);
        $this->assertDatabaseHas('categories', ['slug' => 'category-3']);
    }

    /**
     * RecipeKit creation and update disambiguate colliding slugs without 500 errors.
     */
    public function test_recipe_kit_slug_collision_stress(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $resp = $this->actingAs($this->superAdmin, 'admin')->post(route('admin.recipe-kits.store'), [
                'name' => 'Palak Paneer Meal Kit',
                'slug' => '',
                'price' => 18.00,
                'servings' => '4 servings',
                'cooking_time' => '25 mins',
                'difficulty' => 'Easy',
            ]);
            $resp->assertRedirect();
        }

        $this->assertDatabaseHas('recipe_kits', ['slug' => 'palak-paneer-meal-kit']);
        $this->assertDatabaseHas('recipe_kits', ['slug' => 'palak-paneer-meal-kit-1']);
        $this->assertDatabaseHas('recipe_kits', ['slug' => 'palak-paneer-meal-kit-5']);
    }

    // =========================================================================
    // SECTION 5: DATABASE ATOMICITY & FAULT INJECTION SIMULATION
    // =========================================================================

    /**
     * Fault injection: If an exception occurs inside AdminOrderController::refundOrderItem
     * transaction, intermediate writes (item refunded amount, product stock zeroing, order refunded_total)
     * roll back completely without partial state persistence.
     */
    public function test_admin_order_item_refund_rolls_back_atomically_on_database_failure(): void
    {
        $product = Product::factory()->create(['stock' => 15]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'total' => 60.00,
            'refunded_total' => 0.00,
            'notes' => 'Original packing note',
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 20.00,
            'quantity' => 2,
            'refunded_quantity' => 0,
            'total_price' => 40.00,
            'is_packed' => true,
        ]);

        // Inject simulated failure during Order update within transaction
        $interceptOrderUpdate = true;
        DB::listen(function ($query) use (&$interceptOrderUpdate) {
            if ($interceptOrderUpdate && str_contains(strtolower($query->sql), 'update') && str_contains(strtolower($query->sql), 'orders')) {
                throw new \RuntimeException('ADVERSARIAL_INJECTED_ORDER_UPDATE_FAILURE');
            }
        });

        $this->withoutExceptionHandling();
        $caught = false;

        try {
            $this->actingAs($this->superAdmin, 'admin')->post(route('admin.orders.items.refund', [$order, $item]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Damaged during packing',
            ]);
        } catch (\Throwable $e) {
            $caught = true;
            $this->assertStringContainsString('ADVERSARIAL_INJECTED_ORDER_UPDATE_FAILURE', $e->getMessage());
        } finally {
            $interceptOrderUpdate = false;
        }

        $this->assertTrue($caught, 'Expected simulated database failure was not thrown.');

        // Re-read entities from database
        $order->refresh();
        $item->refresh();
        $product->refresh();

        // ATOMICITY VERIFICATION:
        // 1. Item refunded_quantity and amount MUST remain 0
        $this->assertSame(0, (int) $item->refunded_quantity);
        $this->assertEquals(0.00, (float) $item->refunded_amount);
        $this->assertTrue((bool) $item->is_packed);

        // 2. Product stock MUST NOT be zeroed out
        $this->assertSame(15, $product->stock);

        // 3. Order refunded_total and notes MUST remain unmutated
        $this->assertEquals(0.00, (float) $order->refunded_total);
        $this->assertSame('Original packing note', $order->notes);
        $this->assertSame('packing', $order->status);
    }

    /**
     * If Stripe partial refund throws an exception, no database transaction is executed
     * and order remains in its active state with untouched stock.
     */
    public function test_admin_item_refund_aborts_before_transaction_when_stripe_fails(): void
    {
        $product = Product::factory()->create(['stock' => 8]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'total' => 50.00,
            'refunded_total' => 0.00,
            'stripe_payment_id' => 'pi_test_stripe_throw',
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 2,
            'total_price' => 50.00,
        ]);

        $mockStripe = Mockery::mock(StripePayments::class);
        $mockStripe->shouldReceive('isEnabled')->andReturn(true);
        $mockStripe->shouldReceive('refundPartialPayment')
            ->andThrow(new \Exception('Stripe cardholder decline'));
        $this->app->instance(StripePayments::class, $mockStripe);

        $response = $this->actingAs($this->superAdmin, 'admin')->postJson(route('admin.orders.items.refund', [$order, $item]), [
            'quantity' => 1,
            'zero_product_stock' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['item']);

        $order->refresh();
        $item->refresh();
        $product->refresh();

        $this->assertSame(0, (int) $item->refunded_quantity);
        $this->assertSame(8, $product->stock);
        $this->assertEquals(0.00, (float) $order->refunded_total);
        $this->assertSame('packing', $order->status);
    }

    /**
     * Sequential item refunds until all items are refunded: Order automatically transitions to 'cancelled'.
     */
    public function test_complete_item_refund_automatically_cancels_order(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'packing',
            'total' => 40.00,
            'refunded_total' => 0.00,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 20.00,
            'quantity' => 2,
            'total_price' => 40.00,
            'is_packed' => true,
        ]);

        // Refund 1 unit
        $this->actingAs($this->superAdmin, 'admin')->postJson(route('admin.orders.items.refund', [$order, $item]), [
            'quantity' => 1,
            'zero_product_stock' => false,
        ])->assertOk();

        $order->refresh();
        $item->refresh();
        $this->assertSame(1, (int) $item->refunded_quantity);
        $this->assertSame('packing', $order->status);

        // Refund remaining 1 unit
        $this->actingAs($this->superAdmin, 'admin')->postJson(route('admin.orders.items.refund', [$order, $item]), [
            'quantity' => 1,
            'zero_product_stock' => false,
        ])->assertOk();

        $order->refresh();
        $item->refresh();
        $this->assertSame(2, (int) $item->refunded_quantity);
        $this->assertFalse((bool) $item->is_packed);
        // Because 0 active units remain, order must be automatically cancelled
        $this->assertSame('cancelled', $order->status);

        // Further refund attempt on fully refunded item is rejected with 422
        $this->actingAs($this->superAdmin, 'admin')->postJson(route('admin.orders.items.refund', [$order, $item]), [
            'quantity' => 1,
        ])->assertStatus(422);
    }

    // =========================================================================
    // SECTION 6: KDS METRICS & SUB-SECOND DAY BOUNDARY VERIFICATION
    // =========================================================================

    /**
     * KDS Order summary metrics strictly respect Carbon startOfDay and endOfDay boundaries
     * and accurately tally today vs all-time counts and revenue net of partial refunds.
     */
    public function test_kds_metrics_strict_day_boundary_and_revenue_net_calculation(): void
    {
        // 1. Order exactly at start of today (00:00:00) -> completed, total $100, refund $20 -> Net $80
        Order::factory()->create([
            'status' => 'completed',
            'total' => 100.00,
            'refunded_total' => 20.00,
            'created_at' => Carbon::today()->startOfDay(),
        ]);

        // 2. Order exactly at end of today (23:59:59) -> ready_for_pickup, total $70, refund $0 -> Net $70
        Order::factory()->create([
            'status' => 'ready_for_pickup',
            'total' => 70.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::today()->endOfDay(),
        ]);

        // 3. Order midday today -> cancelled, total $150, refund $150 -> Cancelled today, Net revenue $0
        Order::factory()->create([
            'status' => 'cancelled',
            'total' => 150.00,
            'refunded_total' => 150.00,
            'created_at' => Carbon::today()->setTime(14, 30, 0),
        ]);

        // 4. Order midday today -> packing, total $50, refund $0 -> Net $50
        Order::factory()->create([
            'status' => 'packing',
            'total' => 50.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::today()->setTime(11, 0, 0),
        ]);

        // 5. Order 1 second before start of today (yesterday 23:59:59) -> completed, total $200 -> NOT today
        Order::factory()->create([
            'status' => 'completed',
            'total' => 200.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::today()->startOfDay()->subSecond(),
        ]);

        // 6. Order 1 second after end of today (tomorrow 00:00:00) -> completed, total $300 -> NOT today
        Order::factory()->create([
            'status' => 'completed',
            'total' => 300.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::today()->endOfDay()->addSecond(),
        ]);

        $response = $this->actingAs($this->superAdmin, 'admin')->get(route('admin.orders.index'));
        $response->assertOk();

        // Expected metrics:
        // total_all: 6
        // total_today: 4 (orders 1, 2, 3, 4)
        // completed: 3 (orders 1, 5, 6)
        // completed_today: 1 (order 1)
        // ready_for_pickup: 1 (order 2)
        // packing: 1 (order 4)
        // cancelled: 1 (order 3)
        // cancelled_today: 1 (order 3)
        // revenue_today: $80 + $70 + $50 = $200.00 (order 3 cancelled is excluded)
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('stats.total_all', 6)
            ->where('stats.total_today', 4)
            ->where('stats.completed', 3)
            ->where('stats.completed_today', 1)
            ->where('stats.ready_for_pickup', 1)
            ->where('stats.packing', 1)
            ->where('stats.cancelled', 1)
            ->where('stats.cancelled_today', 1)
            ->where('stats.revenue_today', 200)
        );
    }

    /**
     * Item refund validation prevents refunding items across different order boundaries.
     */
    public function test_item_refund_fails_with_404_if_item_does_not_belong_to_order(): void
    {
        $orderA = Order::factory()->create(['status' => 'packing']);
        $orderB = Order::factory()->create(['status' => 'packing']);

        $itemB = $orderB->items()->create([
            'name' => 'Item Belonging to B',
            'unit_price' => 15.00,
            'quantity' => 1,
            'total_price' => 15.00,
        ]);

        // Attempt to refund itemB via orderA URL
        $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.orders.items.refund', [$orderA, $itemB]), [
                'quantity' => 1,
            ])
            ->assertStatus(404);
    }

    /**
     * Heavy discount cap: Item refund is strictly capped at remaining order balance ($order->total - $order->refunded_total),
     * preventing negative net revenue or refund amounts exceeding customer charge.
     */
    public function test_refund_amount_is_capped_strictly_by_remaining_order_balance(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        // Order with $50.00 subtotal, $45.00 discount -> total charge $5.00
        $order = Order::factory()->create([
            'status' => 'packing',
            'subtotal' => 50.00,
            'discount' => 45.00,
            'total' => 5.00,
            'refunded_total' => 0.00,
        ]);

        // Item unit price is $25.00 (which exceeds the $5.00 paid total)
        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 1,
            'total_price' => 25.00,
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order, $item]), [
                'quantity' => 1,
                'zero_product_stock' => false,
            ])
            ->assertOk();

        $order->refresh();
        $item->refresh();

        // Refund amount must NOT be $25.00; it MUST be capped at $5.00 (the entire order total paid)
        $this->assertEquals(5.00, (float) $item->refunded_amount);
        $this->assertEquals(5.00, (float) $order->refunded_total);
        $this->assertSame(1, (int) $item->refunded_quantity);
    }

    /**
     * Bulk packing: Bulk packing marks all active items as packed,
     * but strictly skips items that have been 100% refunded (OOS).
     */
    public function test_bulk_packing_updates_active_items_but_excludes_fully_refunded_items(): void
    {
        $order = Order::factory()->create(['status' => 'packing']);

        // Item 1: Active
        $activeItem = $order->items()->create([
            'name' => 'Active Item',
            'unit_price' => 10.00,
            'quantity' => 2,
            'refunded_quantity' => 0,
            'total_price' => 20.00,
            'is_packed' => false,
        ]);

        // Item 2: Fully refunded (2 of 2 refunded)
        $refundedItem = $order->items()->create([
            'name' => 'Refunded Item',
            'unit_price' => 15.00,
            'quantity' => 2,
            'refunded_quantity' => 2,
            'total_price' => 30.00,
            'is_packed' => false,
        ]);

        // Bulk pack all items in order
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->patchJson(route('admin.orders.items.packed', $order), [
                'is_packed' => true,
            ]);

        $response->assertOk();

        $activeItem->refresh();
        $refundedItem->refresh();

        $this->assertTrue((bool) $activeItem->is_packed, 'Active item should be marked packed.');
        $this->assertFalse((bool) $refundedItem->is_packed, 'Fully refunded item must NOT be marked packed.');
    }

    /**
     * Item refunds are rejected with 422 when order is completed or cancelled.
     */
    public function test_cannot_refund_items_on_completed_or_cancelled_orders(): void
    {
        // 1. Completed order
        $completedOrder = Order::factory()->create(['status' => 'completed']);
        $item1 = $completedOrder->items()->create([
            'name' => 'Completed Item',
            'unit_price' => 10.00,
            'quantity' => 1,
            'total_price' => 10.00,
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$completedOrder, $item1]), [
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item']);

        // 2. Cancelled order
        $cancelledOrder = Order::factory()->create(['status' => 'cancelled']);
        $item2 = $cancelledOrder->items()->create([
            'name' => 'Cancelled Item',
            'unit_price' => 10.00,
            'quantity' => 1,
            'total_price' => 10.00,
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$cancelledOrder, $item2]), [
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item']);
    }

    /**
     * Financial history retention: Deleting a customer preserves their orders with user_id = null.
     */
    public function test_customer_deletion_preserves_order_audit_trail_with_null_user_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Ephemeral Customer',
            'email' => 'ephemeral@customer.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => '#MM-20261021-9999-ABCD',
            'total' => 150.00,
            'status' => 'completed',
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.customers.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // Order MUST NOT be deleted; foreign key nullOnDelete preserves financial records
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => null,
            'total' => 150.00,
        ]);
    }

    /**
     * Role sync rejects non-existent roles.
     */
    public function test_role_sync_rejects_invalid_roles(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->postJson(route('admin.admins.roles.sync', $this->unprivilegedAdmin), [
                'roles' => ['FictionalRole'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }
}
