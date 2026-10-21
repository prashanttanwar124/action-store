<?php

namespace Tests\Feature\Challenger;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Challenger Test Suite for Milestone 4 (ADM-02, ADM-03, ADM-04):
 * Slug Disambiguation, Customer Lifetime Metrics Net of Refunds, and KDS Timezone Day Boundaries.
 */
class Milestone4SlugAndMetricsTest extends TestCase
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
     * ADM-02 EMPIRICAL TEST:
     * Product creation with identical names auto-disambiguates slugs into slug, slug-1, slug-2 without 500 error.
     */
    public function test_product_store_auto_disambiguates_colliding_slugs(): void
    {
        // Create 1st product with name "Organic Toor Dal"
        $resp1 = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Organic Toor Dal',
            'slug' => '',
            'category' => 'lentils',
            'price' => 7.99,
        ]);
        $resp1->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Organic Toor Dal',
            'slug' => 'organic-toor-dal',
        ]);

        // Create 2nd product with same name
        $resp2 = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Organic Toor Dal',
            'slug' => '',
            'category' => 'lentils',
            'price' => 8.49,
        ]);
        $resp2->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Organic Toor Dal',
            'slug' => 'organic-toor-dal-1',
        ]);

        // Create 3rd product with same name
        $resp3 = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Organic Toor Dal',
            'slug' => null,
            'category' => 'lentils',
            'price' => 8.99,
        ]);
        $resp3->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Organic Toor Dal',
            'slug' => 'organic-toor-dal-2',
        ]);
    }

    /**
     * ADM-02 EMPIRICAL TEST:
     * Product update auto-disambiguates colliding slug, and does not alter slug when updating with same name.
     */
    public function test_product_update_disambiguation_and_idempotency(): void
    {
        $prod1 = Product::factory()->create([
            'name' => 'Masala Chai Tea',
            'slug' => 'masala-chai-tea',
        ]);
        $prod2 = Product::factory()->create([
            'name' => 'Masala Chai Tea',
            'slug' => 'masala-chai-tea-1',
        ]);
        $prod3 = Product::factory()->create([
            'name' => 'Ginger Tea',
            'slug' => 'ginger-tea',
        ]);

        // 1. Update prod3 to collide with "Masala Chai Tea"
        $resp = $this->actingAs($this->admin, 'admin')->put(route('admin.products.update', $prod3->id), [
            'name' => 'Masala Chai Tea',
            'slug' => '',
            'category' => 'beverages',
            'price' => 5.99,
        ]);
        $resp->assertRedirect();
        $this->assertDatabaseHas('products', [
            'id' => $prod3->id,
            'slug' => 'masala-chai-tea-2',
        ]);

        // 2. Idempotency check: Updating prod2 with same name and empty slug should KEEP masala-chai-tea-1
        $respSelf = $this->actingAs($this->admin, 'admin')->put(route('admin.products.update', $prod2->id), [
            'name' => 'Masala Chai Tea',
            'slug' => '',
            'category' => 'beverages',
            'price' => 6.99,
        ]);
        $respSelf->assertRedirect();
        $this->assertEquals('masala-chai-tea-1', $prod2->fresh()->slug);

        // 3. Gap filling: If masala-chai-tea-1 is deleted, a new collision should take masala-chai-tea-1
        $prod2->delete();
        $prod4 = Product::factory()->create([
            'name' => 'Herbal Tea',
            'slug' => 'herbal-tea',
        ]);
        $respGap = $this->actingAs($this->admin, 'admin')->put(route('admin.products.update', $prod4->id), [
            'name' => 'Masala Chai Tea',
            'slug' => '',
            'category' => 'beverages',
            'price' => 4.99,
        ]);
        $respGap->assertRedirect();
        $this->assertEquals('masala-chai-tea-1', $prod4->fresh()->slug);
    }

    /**
     * ADM-02 EMPIRICAL TEST:
     * Category create and update auto-disambiguates slugs and cascades to products on update.
     */
    public function test_category_slug_disambiguation_and_product_cascade(): void
    {
        // Create 3 categories with colliding name
        for ($i = 0; $i < 3; $i++) {
            $resp = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), [
                'name' => 'Gourmet Sauces',
                'slug' => null,
            ]);
            $resp->assertRedirect();
        }

        $this->assertDatabaseHas('categories', ['name' => 'Gourmet Sauces', 'slug' => 'gourmet-sauces']);
        $this->assertDatabaseHas('categories', ['name' => 'Gourmet Sauces', 'slug' => 'gourmet-sauces-1']);
        $this->assertDatabaseHas('categories', ['name' => 'Gourmet Sauces', 'slug' => 'gourmet-sauces-2']);

        // Update another category to "Gourmet Sauces"
        $otherCat = Category::create([
            'name' => 'Condiments',
            'slug' => 'condiments',
        ]);

        $productInCondiments = Product::factory()->create([
            'category' => 'condiments',
            'category_title' => 'Condiments',
        ]);

        $updateResp = $this->actingAs($this->admin, 'admin')->put(route('admin.categories.update', $otherCat->id), [
            'name' => 'Gourmet Sauces',
            'slug' => null,
        ]);
        $updateResp->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $otherCat->id,
            'slug' => 'gourmet-sauces-3',
        ]);

        // Product category should cascade from condiments to gourmet-sauces-3
        $this->assertEquals('gourmet-sauces-3', $productInCondiments->fresh()->category);
        $this->assertEquals('Gourmet Sauces', $productInCondiments->fresh()->category_title);
    }

    /**
     * ADM-02 EMPIRICAL TEST:
     * RecipeKit create and update auto-disambiguates colliding slugs without 500 error.
     */
    public function test_recipe_kit_slug_disambiguation(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $resp = $this->actingAs($this->admin, 'admin')->post(route('admin.recipe-kits.store'), [
                'name' => 'Kadhai Paneer Kit',
                'slug' => '',
                'price' => 19.99,
                'servings' => '4 servings',
                'cooking_time' => '30 mins',
                'difficulty' => 'Medium',
            ]);
            $resp->assertRedirect();
        }

        $this->assertDatabaseHas('recipe_kits', ['name' => 'Kadhai Paneer Kit', 'slug' => 'kadhai-paneer-kit']);
        $this->assertDatabaseHas('recipe_kits', ['name' => 'Kadhai Paneer Kit', 'slug' => 'kadhai-paneer-kit-1']);
        $this->assertDatabaseHas('recipe_kits', ['name' => 'Kadhai Paneer Kit', 'slug' => 'kadhai-paneer-kit-2']);

        // Update existing kit keeping same name
        $kit1 = RecipeKit::where('slug', 'kadhai-paneer-kit')->firstOrFail();
        $updateResp = $this->actingAs($this->admin, 'admin')->put(route('admin.recipe-kits.update', $kit1->id), [
            'name' => 'Kadhai Paneer Kit',
            'slug' => '',
            'price' => 21.99,
        ]);
        $updateResp->assertRedirect();
        $this->assertEquals('kadhai-paneer-kit', $kit1->fresh()->slug);
    }

    /**
     * ADM-02 EMPIRICAL TEST:
     * Empty slugifiable strings fallback to default entity name (product, category, recipe-kit)
     * and auto-disambiguate on collision.
     */
    public function test_non_alphanumeric_fallback_slug_auto_disambiguation(): void
    {
        // 1. Products with non-slugifiable symbols/spaces
        $respP1 = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => '🍚🍲',
            'slug' => '',
            'category' => 'grains',
            'price' => 5.00,
        ]);
        $respP1->assertRedirect();
        $this->assertDatabaseHas('products', ['slug' => 'product']);

        $respP2 = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => '🍚🍲',
            'slug' => '',
            'category' => 'grains',
            'price' => 6.00,
        ]);
        $respP2->assertRedirect();
        $this->assertDatabaseHas('products', ['slug' => 'product-1']);

        // 2. Categories with non-slugifiable symbols
        $respC1 = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), [
            'name' => '🔥🔥🔥',
            'slug' => '',
        ]);
        $respC1->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'category']);

        $respC2 = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), [
            'name' => '🔥🔥🔥',
            'slug' => '',
        ]);
        $respC2->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'category-1']);
    }

    /**
     * ADM-02 EMPIRICAL TEST:
     * Supplying an explicit duplicate slug triggers 422 validation error instead of 500 QueryException.
     */
    public function test_explicit_colliding_slug_returns_422_validation_error(): void
    {
        Product::factory()->create([
            'name' => 'First Product',
            'slug' => 'unique-explicit-slug',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.products.store'), [
            'name' => 'Second Product',
            'slug' => 'unique-explicit-slug',
            'category' => 'snacks',
            'price' => 9.99,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * ADM-03 EMPIRICAL TEST:
     * Customer total_spent calculation across index, show, and sorting:
     * - Partially refunded orders calculate as total - refunded_total.
     * - Fully refunded orders calculate as total - refunded_total = 0.
     * - Cancelled orders are completely excluded (even if unrefunded or refunded).
     * - Customers with 0 orders show total_spent = 0.00.
     * - Metrics calculate aggregate total_revenue correctly.
     */
    public function test_customer_metrics_and_total_spent_comprehensive(): void
    {
        // Customer A: Diverse active orders, partial refund, and cancelled orders
        $userA = User::factory()->create([
            'name' => 'Alpha Customer',
            'email' => 'alpha@test.com',
        ]);

        // Order 1: Completed, $120.00 total, $30.00 refunded -> Net $90.00
        Order::factory()->create([
            'user_id' => $userA->id,
            'status' => 'completed',
            'total' => 120.00,
            'refunded_total' => 30.00,
        ]);

        // Order 2: Packing, $60.00 total, $15.00 refunded -> Net $45.00
        Order::factory()->create([
            'user_id' => $userA->id,
            'status' => 'packing',
            'total' => 60.00,
            'refunded_total' => 15.00,
        ]);

        // Order 3: Confirmed, $45.00 total, $0 refunded -> Net $45.00
        Order::factory()->create([
            'user_id' => $userA->id,
            'status' => 'confirmed',
            'total' => 45.00,
            'refunded_total' => 0.00,
        ]);

        // Order 4: Cancelled with refund recorded -> EXCLUDED ($0.00)
        Order::factory()->create([
            'user_id' => $userA->id,
            'status' => 'cancelled',
            'total' => 250.00,
            'refunded_total' => 250.00,
        ]);

        // Order 5: Cancelled without refund recorded -> MUST STILL BE EXCLUDED ($0.00)
        Order::factory()->create([
            'user_id' => $userA->id,
            'status' => 'cancelled',
            'total' => 100.00,
            'refunded_total' => 0.00,
        ]);
        // Expected total_spent for User A: 90.00 + 45.00 + 45.00 = 180.00

        // Customer B: 100% refunded completed order
        $userB = User::factory()->create([
            'name' => 'Beta Customer',
            'email' => 'beta@test.com',
        ]);
        Order::factory()->create([
            'user_id' => $userB->id,
            'status' => 'completed',
            'total' => 50.00,
            'refunded_total' => 50.00,
        ]);
        // Expected total_spent for User B: 0.00

        // Customer C: Only cancelled orders
        $userC = User::factory()->create([
            'name' => 'Gamma Customer',
            'email' => 'gamma@test.com',
        ]);
        Order::factory()->create([
            'user_id' => $userC->id,
            'status' => 'cancelled',
            'total' => 500.00,
            'refunded_total' => 500.00,
        ]);
        // Expected total_spent for User C: 0.00

        // Customer D: Zero orders
        $userD = User::factory()->create([
            'name' => 'Delta Customer',
            'email' => 'delta@test.com',
        ]);
        // Expected total_spent for User D: 0.00

        // 1. Test Admin Customer Index
        $indexResp = $this->actingAs($this->admin, 'admin')->get(route('admin.customers.index', [
            'sort' => 'highest_spend',
        ]));
        $indexResp->assertOk();
        $indexResp->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            // User A must be first in highest_spend with 180.00
            ->where('customers.data.0.id', $userA->id)
            ->where('customers.data.0.total_spent', 180)
            ->where('metrics.total_revenue', 180)
            ->where('metrics.total_orders', 7)
            ->where('metrics.total_customers', 4)
            ->where('metrics.active_shoppers', 3) // A, B, C have orders
            ->where('metrics.average_spend', 45) // 180 / 4 customers
        );

        // 2. Test Customer Show Endpoints
        $showAResp = $this->actingAs($this->admin, 'admin')->getJson(route('admin.customers.show', $userA->id));
        $showAResp->assertOk()
            ->assertJsonPath('customer.total_spent', 180)
            ->assertJsonPath('customer.orders_count', 5);

        $showBResp = $this->actingAs($this->admin, 'admin')->getJson(route('admin.customers.show', $userB->id));
        $showBResp->assertOk()
            ->assertJsonPath('customer.total_spent', 0)
            ->assertJsonPath('customer.orders_count', 1);

        $showCResp = $this->actingAs($this->admin, 'admin')->getJson(route('admin.customers.show', $userC->id));
        $showCResp->assertOk()
            ->assertJsonPath('customer.total_spent', 0)
            ->assertJsonPath('customer.orders_count', 1);

        $showDResp = $this->actingAs($this->admin, 'admin')->getJson(route('admin.customers.show', $userD->id));
        $showDResp->assertOk()
            ->assertJsonPath('customer.total_spent', 0)
            ->assertJsonPath('customer.orders_count', 0);
    }

    /**
     * ADM-04 EMPIRICAL TEST:
     * KDS order metrics accurately bucket orders on day boundaries (00:00:00 to 23:59:59)
     * and subtract refunds while excluding cancelled orders from revenue.
     */
    public function test_kds_order_metrics_strict_day_boundaries(): void
    {
        // 1. Order created today at 00:00:01 (completed, total $50, refund $10) -> revenue $40
        Order::factory()->create([
            'status' => 'completed',
            'total' => 50.00,
            'refunded_total' => 10.00,
            'created_at' => Carbon::today()->startOfDay()->addSecond(),
        ]);

        // 2. Order created today at 23:59:58 (ready_for_pickup, total $75, refund $0) -> revenue $75
        Order::factory()->create([
            'status' => 'ready_for_pickup',
            'total' => 75.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::today()->endOfDay()->subSeconds(1),
        ]);

        // 3. Order created today at 12:00:00 (cancelled, total $100) -> cancelled_today, revenue $0
        Order::factory()->create([
            'status' => 'cancelled',
            'total' => 100.00,
            'refunded_total' => 100.00,
            'created_at' => Carbon::today()->setTime(12, 0, 0),
        ]);

        // 4. Order created yesterday at 23:59:59 (completed, total $200) -> NOT today
        Order::factory()->create([
            'status' => 'completed',
            'total' => 200.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::yesterday()->endOfDay(),
        ]);

        // 5. Order created tomorrow at 00:00:01 (future timestamp, completed, total $300) -> NOT today
        Order::factory()->create([
            'status' => 'completed',
            'total' => 300.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::tomorrow()->startOfDay()->addSecond(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.index'));
        $response->assertOk();

        // Expected metrics:
        // total_all: 5
        // total_today: 3 (orders 1, 2, 3)
        // completed: 3 (orders 1, 4, 5)
        // completed_today: 1 (order 1)
        // ready_for_pickup: 1 (order 2)
        // cancelled: 1 (order 3)
        // cancelled_today: 1 (order 3)
        // revenue_today: $40 + $75 = $115.00
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('stats.total_all', 5)
            ->where('stats.total_today', 3)
            ->where('stats.completed', 3)
            ->where('stats.completed_today', 1)
            ->where('stats.ready_for_pickup', 1)
            ->where('stats.cancelled', 1)
            ->where('stats.cancelled_today', 1)
            ->where('stats.revenue_today', 115)
        );
    }
}
