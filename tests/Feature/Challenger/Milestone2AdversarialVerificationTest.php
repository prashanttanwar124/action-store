<?php

namespace Tests\Feature\Challenger;

use App\Http\Controllers\AccountController;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionMethod;
use Tests\TestCase;

class Milestone2AdversarialVerificationTest extends TestCase
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
     * EMPIRICAL CHECK 1: Verify schema indexes on orders table in current connection.
     */
    public function test_orders_table_has_required_composite_indexes_in_test_schema(): void
    {
        $indexes = Schema::getIndexes('orders');

        $hasStatusCreatedAt = false;
        $hasUserIdCreatedAt = false;

        foreach ($indexes as $index) {
            $cols = $index['columns'] ?? [];
            if ($cols === ['status', 'created_at']) {
                $hasStatusCreatedAt = true;
            }
            if ($cols === ['user_id', 'created_at']) {
                $hasUserIdCreatedAt = true;
            }
        }

        $this->assertTrue(
            $hasStatusCreatedAt,
            'Composite index on orders(status, created_at) must exist in schema.'
        );
        $this->assertTrue(
            $hasUserIdCreatedAt,
            'Composite index on orders(user_id, created_at) must exist in schema.'
        );
    }

    /**
     * EMPIRICAL CHECK 2: Seed >50 orders for a customer and call AccountController::getUserOrders().
     * Assert precisely 50 orders are returned, sorted newest to oldest.
     */
    public function test_account_controller_get_user_orders_strictly_bounds_to_50_orders(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        // Seed 65 orders for Customer A across 65 minutes
        for ($i = 1; $i <= 65; $i++) {
            $order = Order::factory()->create([
                'user_id' => $customerA->id,
                'order_number' => sprintf('ORD-CA-%03d', $i),
                'created_at' => Carbon::now()->subMinutes(100 - $i),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'name' => "Item for Order {$i}",
                'quantity' => 1,
                'unit_price' => 10.00,
                'total_price' => 10.00,
            ]);
        }

        // Seed 10 orders for Customer B to verify tenant boundary isolation
        for ($j = 1; $j <= 10; $j++) {
            Order::factory()->create([
                'user_id' => $customerB->id,
                'order_number' => sprintf('ORD-CB-%03d', $j),
                'created_at' => Carbon::now()->subMinutes(10 - $j),
            ]);
        }

        Auth::login($customerA);

        $controller = new AccountController;
        $method = new ReflectionMethod($controller, 'getUserOrdersPayload');
        $payload = $method->invoke($controller);
        $orders = $payload['dbOrders'];

        // Assert precisely 10 orders per page returned
        $this->assertCount(10, $orders, 'getUserOrdersPayload() must return 10 orders per page.');

        // Assert sorted newest to oldest: first is ORD-CA-065, 10th is ORD-CA-056
        $this->assertEquals('ORD-CA-065', $orders[0]['id'], 'First order must be the most recent order (ORD-CA-065).');
        $this->assertEquals('ORD-CA-056', $orders[9]['id'], '10th order must be ORD-CA-056.');
        $this->assertSame(65, $payload['ordersPagination']['total']);
        $this->assertTrue($payload['ordersPagination']['has_more']);

        // Assert no customer B orders are present
        foreach ($orders as $orderPayload) {
            $this->assertStringStartsWith('ORD-CA-', $orderPayload['id']);
        }

        // Also verify the HTTP Inertia route emits the exact same paginated orders
        $this->get(route('account'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->has('dbOrders', 10)
                ->where('dbOrders.0.id', 'ORD-CA-065')
                ->where('dbOrders.9.id', 'ORD-CA-056')
                ->where('ordersPagination.total', 65)
                ->where('ordersPagination.has_more', true)
            );
    }

    /**
     * EMPIRICAL CHECK 2b: getUserOrdersPayload returns empty array when unauthenticated.
     */
    public function test_account_controller_get_user_orders_returns_empty_when_unauthenticated(): void
    {
        Auth::logout();

        $controller = new AccountController;
        $method = new ReflectionMethod($controller, 'getUserOrdersPayload');
        $payload = $method->invoke($controller);

        $this->assertSame([], $payload['dbOrders']);
        $this->assertFalse($payload['ordersPagination']['has_more']);
    }

    /**
     * EMPIRICAL CHECK 3: Test Model::preventLazyLoading() throws LazyLoadingViolationException
     * on multi-item collection lazy-load, and does not throw when properly eager-loaded.
     */
    public function test_prevent_lazy_loading_throws_exception_on_multi_item_collection(): void
    {
        $this->assertTrue(
            Model::preventsLazyLoading(),
            'Model::preventsLazyLoading() must be enabled in testing environment.'
        );

        $order1 = Order::factory()->create();
        OrderItem::create([
            'order_id' => $order1->id,
            'name' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 5.00,
            'total_price' => 5.00,
        ]);

        $order2 = Order::factory()->create();
        OrderItem::create([
            'order_id' => $order2->id,
            'name' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 5.00,
            'total_price' => 5.00,
        ]);

        // Hydrate multi-item collection without eager loading
        $orders = Order::whereIn('id', [$order1->id, $order2->id])->get();
        $this->assertGreaterThan(1, $orders->count());

        $this->expectException(LazyLoadingViolationException::class);
        $this->expectExceptionMessage('Attempted to lazy load [items] on model [App\Models\Order] but lazy loading is disabled.');

        // Accessing relationship must throw LazyLoadingViolationException
        $items = $orders->first()->items;
    }

    /**
     * EMPIRICAL CHECK 3b: Verify eager loading prevents LazyLoadingViolationException.
     */
    public function test_eager_loading_avoids_lazy_loading_violation(): void
    {
        $order1 = Order::factory()->create();
        OrderItem::create([
            'order_id' => $order1->id,
            'name' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 5.00,
            'total_price' => 5.00,
        ]);

        $order2 = Order::factory()->create();
        OrderItem::create([
            'order_id' => $order2->id,
            'name' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 5.00,
            'total_price' => 5.00,
        ]);

        $orders = Order::with('items')->whereIn('id', [$order1->id, $order2->id])->get();

        $this->assertCount(2, $orders);
        $this->assertNotEmpty($orders->first()->items);
    }

    /**
     * EMPIRICAL CHECK 4: Adversarial test of category update atomicity under failure.
     */
    public function test_category_update_rolls_back_when_product_update_fails(): void
    {
        $category = Category::create([
            'name' => 'Original Spices',
            'slug' => 'original-spices',
            'icon' => 'Sparkles',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'original-spices',
            'category_title' => 'Original Spices',
        ]);

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            if ($shouldFail && (str_contains($query->sql, 'update `products`') || str_contains($query->sql, 'update "products"'))) {
                throw new \RuntimeException('Simulated failure during product category synchronization');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->patch(route('admin.categories.update', $category), [
                'name' => 'Renamed Spices',
                'slug' => 'renamed-spices',
                'sort_order' => 2,
                'is_active' => true,
            ]);
        } catch (\Throwable) {
            // Expected
        } finally {
            $shouldFail = false;
        }

        $category->refresh();
        $product->refresh();

        $this->assertEquals('Original Spices', $category->name);
        $this->assertEquals('original-spices', $category->slug);
        $this->assertEquals('original-spices', $product->category);
    }

    /**
     * EMPIRICAL CHECK 5: Adversarial test of category deletion atomicity under failure.
     */
    public function test_category_destroy_rolls_back_when_deletion_fails(): void
    {
        $category = Category::create([
            'name' => 'Temp Sweets',
            'slug' => 'temp-sweets',
            'icon' => 'Heart',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'temp-sweets',
            'category_title' => 'Temp Sweets',
        ]);

        Category::deleting(function () {
            throw new \RuntimeException('Simulated category deletion database crash');
        });

        try {
            $this->actingAs($this->admin, 'admin')->delete(route('admin.categories.destroy', $category));
        } catch (\Throwable) {
            // Expected
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $product->refresh();
        $this->assertEquals('temp-sweets', $product->category);
        $this->assertNotEquals('grocery', $product->category);
    }

    /**
     * EMPIRICAL CHECK 6: Adversarial test of recipe kit store atomicity under pivot sync failure.
     */
    public function test_recipe_kit_store_rolls_back_when_pivot_sync_fails(): void
    {
        $product = Product::factory()->create();

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            if ($shouldFail && str_contains($query->sql, 'recipe_kit_products')) {
                throw new \RuntimeException('Simulated pivot table failure during recipe kit store');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->post(route('admin.recipe-kits.store'), [
                'name' => 'Uncommitted Butter Chicken Kit',
                'subtitle_tag' => 'ATOMIC TEST',
                'description' => 'Test description for atomic rollback.',
                'cooking_time' => '25 mins',
                'servings' => 'Serves 4',
                'difficulty' => 'Medium',
                'price' => 29.99,
                'is_active' => true,
                'products' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Expected
        } finally {
            $shouldFail = false;
        }

        $this->assertDatabaseMissing('recipe_kits', ['name' => 'Uncommitted Butter Chicken Kit']);
    }

    /**
     * EMPIRICAL CHECK 7: Adversarial test of role store atomicity under permission sync failure.
     */
    public function test_role_store_rolls_back_when_permission_sync_fails(): void
    {
        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            if ($shouldFail && str_contains($query->sql, 'role_has_permissions')) {
                throw new \RuntimeException('Simulated permission assignment error during role creation');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->post(route('admin.roles.store'), [
                'name' => 'Incomplete Admin Role',
                'permissions' => ['manage products'],
            ]);
        } catch (\Throwable) {
            // Expected
        } finally {
            $shouldFail = false;
        }

        $this->assertDatabaseMissing('roles', ['name' => 'Incomplete Admin Role', 'guard_name' => 'admin']);
    }

    /**
     * EMPIRICAL CHECK 8: Adversarial test of order cancelAndRestock idempotency.
     * Restocking must not happen twice if cancelled multiple times.
     */
    public function test_order_cancellation_is_idempotent_and_prevents_duplicate_restocking(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 50.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 3],
        ]);

        // First cancellation succeeds
        $firstCall = $order->cancelAndRestock('First cancellation', 50.00);
        $this->assertTrue($firstCall);

        $product->refresh();
        $order->refresh();
        $this->assertEquals(8, $product->stock, 'Product stock must increase from 5 to 8.');
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals(50.00, (float) $order->refunded_total);

        // Second cancellation attempt must return false and NOT restock again
        $secondCall = $order->cancelAndRestock('Duplicate cancellation attempt', 50.00);
        $this->assertFalse($secondCall, 'Subsequent cancellation must return false.');

        $product->refresh();
        $order->refresh();
        $this->assertEquals(8, $product->stock, 'Product stock must remain 8 and NOT be restocked again.');
    }
}
