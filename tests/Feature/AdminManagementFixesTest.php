<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionMethod;
use Tests\TestCase;

class AdminManagementFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    /**
     * ADM-01: Completed order cannot be cancelled or refunded via admin status update or cancelOrder.
     */
    public function test_completed_order_cannot_be_cancelled_or_refunded(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 50.00,
            'refunded_total' => 0.00,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 2,
            'total_price' => 50.00,
        ]);

        // Attempting to cancel via PATCH /admin/orders/{order}/status
        $response = $this->actingAs($admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'cancelled']
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertEquals(
            'Completed orders cannot be cancelled or refunded.',
            $response->json('errors.status.0')
        );

        // Verify database state: order remains completed, stock untouched, refund 0
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals(0.0, (float) $order->fresh()->refunded_total);

        // Verify model-level guard on cancelAndRestock()
        $this->assertFalse($order->cancelAndRestock('Direct test attempt', 50.00));
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals(10, $product->fresh()->stock);
    }

    /**
     * ADM-01: Completed order cannot be demoted or reopened to confirmed, packing, or ready_for_pickup.
     */
    public function test_completed_order_cannot_be_reopened_or_demoted(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'completed',
            'total' => 50.00,
            'refunded_total' => 0.00,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 2,
            'total_price' => 50.00,
        ]);

        // 1. Attempting to reopen completed order to any active status must fail with 422
        foreach (['confirmed', 'packing', 'ready_for_pickup'] as $demotedStatus) {
            $response = $this->actingAs($admin, 'admin')->patchJson(
                route('admin.orders.status.update', $order->id),
                ['status' => $demotedStatus]
            );

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);

            $this->assertEquals(
                'Completed orders cannot be modified or reopened.',
                $response->json('errors.status.0')
            );

            // Verify order remains completed in database and stock is untouched
            $this->assertEquals('completed', $order->fresh()->status);
            $this->assertEquals(10, $product->fresh()->stock);
            $this->assertEquals(0.0, (float) $order->fresh()->refunded_total);
        }

        // 2. Verify HTML form redirect behavior also rejects with 302 and session error
        $webResponse = $this->actingAs($admin, 'admin')->patch(
            route('admin.orders.status.update', $order->id),
            ['status' => 'confirmed']
        );

        $webResponse->assertStatus(302);
        $webResponse->assertSessionHasErrors(['status' => 'Completed orders cannot be modified or reopened.']);
        $this->assertEquals('completed', $order->fresh()->status);

        // 3. Verify idempotent transition (completed -> completed) succeeds with 200
        $idempotentResponse = $this->actingAs($admin, 'admin')->patchJson(
            route('admin.orders.status.update', $order->id),
            ['status' => 'completed']
        );

        $idempotentResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEquals('completed', $order->fresh()->status);
    }

    /**
     * ADM-02: Product update auto-disambiguates slug collision when slug is omitted.
     */
    public function test_product_update_disambiguates_colliding_slug(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $product1 = Product::factory()->create([
            'name' => 'Royal Basmati Rice',
            'slug' => 'royal-basmati-rice',
            'price' => 14.99,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Brown Rice',
            'slug' => 'brown-rice',
            'price' => 9.99,
        ]);

        // Update product2 to have the same name as product1 with an empty slug
        $response = $this->actingAs($admin, 'admin')->put(
            route('admin.products.update', $product2->id),
            [
                'name' => 'Royal Basmati Rice',
                'slug' => '',
                'category' => 'grains',
                'price' => 12.99,
            ]
        );

        $response->assertRedirect();

        // Product2 slug should auto-disambiguate to royal-basmati-rice-1
        $this->assertDatabaseHas('products', [
            'id' => $product2->id,
            'slug' => 'royal-basmati-rice-1',
        ]);

        // Product1 retains its original slug
        $this->assertEquals('royal-basmati-rice', $product1->fresh()->slug);
    }

    /**
     * ADM-02: RecipeKit and Category update/store auto-disambiguate colliding slugs.
     */
    public function test_recipe_kit_and_category_update_disambiguates_colliding_slug(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        // 1. Recipe Kit Disambiguation
        $kit1 = RecipeKit::create([
            'name' => 'Butter Chicken Kit',
            'slug' => 'butter-chicken-kit',
            'price' => 24.99,
            'servings' => 'Serves 4',
            'cooking_time' => '30 mins',
            'difficulty' => 'Easy',
            'image' => '/images/products/paneer_curry.jpg',
            'is_active' => true,
        ]);

        $kit2 = RecipeKit::create([
            'name' => 'Tikka Masala Kit',
            'slug' => 'tikka-masala-kit',
            'price' => 24.99,
            'servings' => 'Serves 4',
            'cooking_time' => '30 mins',
            'difficulty' => 'Easy',
            'image' => '/images/products/paneer_curry.jpg',
            'is_active' => true,
        ]);

        $kitResponse = $this->actingAs($admin, 'admin')->put(
            route('admin.recipe-kits.update', $kit2->id),
            [
                'name' => 'Butter Chicken Kit',
                'slug' => '',
                'price' => 29.99,
                'servings' => '4 servings',
                'difficulty' => 'easy',
                'cooking_time' => '30 mins',
            ]
        );

        $kitResponse->assertSessionHasNoErrors();
        $kitResponse->assertRedirect();
        $this->assertDatabaseHas('recipe_kits', [
            'id' => $kit2->id,
            'slug' => 'butter-chicken-kit-1',
        ]);

        // 2. Category Store Disambiguation
        Category::create([
            'name' => 'Spice Blends',
            'slug' => 'spice-blends',
        ]);

        $categoryStoreResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.categories.store'),
            [
                'name' => 'Spice Blends',
                'slug' => null,
            ]
        );

        $categoryStoreResponse->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'name' => 'Spice Blends',
            'slug' => 'spice-blends-1',
        ]);

        // 3. Category Update Disambiguation & Product Cascade
        $category3 = Category::create([
            'name' => 'Pantry Staples',
            'slug' => 'pantry-staples',
        ]);

        $productInCat = Product::factory()->create([
            'category' => 'pantry-staples',
            'category_title' => 'Pantry Staples',
        ]);

        $categoryUpdateResponse = $this->actingAs($admin, 'admin')->put(
            route('admin.categories.update', $category3->id),
            [
                'name' => 'Spice Blends',
                'slug' => null,
            ]
        );

        $categoryUpdateResponse->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'id' => $category3->id,
            'slug' => 'spice-blends-2',
        ]);

        // Verify product category reference cascaded
        $this->assertEquals('spice-blends-2', $productInCat->fresh()->category);
    }

    /**
     * ADM-03: Customer total_spent excludes cancelled orders and subtracts refunded amounts.
     */
    public function test_customer_total_spent_excludes_cancelled_orders_and_subtracts_refunds(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $user = User::factory()->create([
            'name' => 'Aditi Sharma',
            'email' => 'aditi.spend@example.com',
        ]);

        // 1. Completed order: $100 total with $25 refunded -> Net = $75
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total' => 100.00,
            'refunded_total' => 25.00,
        ]);

        // 2. Confirmed order: $50 total with $0 refunded -> Net = $50
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'total' => 50.00,
            'refunded_total' => 0.00,
        ]);

        // 3. Cancelled order: $200 total -> Should be completely excluded (+$0)
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'cancelled',
            'total' => 200.00,
            'refunded_total' => 200.00,
        ]);

        // Expected net lifetime spend: $75 + $50 = $125.00 (not $350.00)
        $response = $this->actingAs($admin, 'admin')->get(
            route('admin.customers.index', ['search' => 'aditi.spend@example.com'])
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            ->where('customers.data.0.total_spent', 125)
            ->where('metrics.total_revenue', 125)
        );

        // Check show endpoint
        $showResponse = $this->actingAs($admin, 'admin')->getJson(
            route('admin.customers.show', $user->id)
        );

        $showResponse->assertOk()
            ->assertJsonPath('customer.total_spent', 125);
    }

    /**
     * ADM-04: KDS daily order metrics use timezone-aware Carbon day boundaries.
     */
    public function test_kds_daily_metrics_uses_timezone_boundaries(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        // Order today late evening: 23:30
        Order::factory()->create([
            'status' => 'completed',
            'total' => 45.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::today()->setTime(23, 30),
        ]);

        // Order today morning cancelled: 08:15
        Order::factory()->create([
            'status' => 'cancelled',
            'total' => 20.00,
            'refunded_total' => 20.00,
            'created_at' => Carbon::today()->setTime(8, 15),
        ]);

        // Order yesterday: should NOT be in total_today, completed_today, or revenue_today
        Order::factory()->create([
            'status' => 'completed',
            'total' => 60.00,
            'refunded_total' => 0.00,
            'created_at' => Carbon::yesterday()->setTime(14, 0),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('stats.total_today', 2)
            ->where('stats.completed_today', 1)
            ->where('stats.cancelled_today', 1)
            ->where('stats.revenue_today', 45)
        );
    }

    /**
     * ADM-05: Product::recipeKits relation has explicit BelongsToMany return type declaration.
     */
    public function test_product_recipe_kits_relation_returns_belongs_to_many(): void
    {
        $reflection = new ReflectionMethod(Product::class, 'recipeKits');

        $this->assertTrue($reflection->hasReturnType(), 'Product::recipeKits() must declare a return type.');
        $this->assertEquals(
            BelongsToMany::class,
            $reflection->getReturnType()?->getName(),
            'Product::recipeKits() return type must be BelongsToMany.'
        );

        $product = new Product;
        $this->assertInstanceOf(BelongsToMany::class, $product->recipeKits());
    }
}
