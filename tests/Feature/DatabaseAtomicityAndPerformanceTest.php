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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DatabaseAtomicityAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->first();
    }

    /**
     * Test category update rollback: if product update throws, category update rolls back.
     */
    public function test_category_update_rolls_back_when_product_synchronization_fails(): void
    {
        $category = Category::create([
            'name' => 'Original Category',
            'slug' => 'original-category',
            'icon' => 'Tag',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'original-category',
            'category_title' => 'Original Category',
        ]);

        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && (str_contains($query->sql, 'update `products`') || str_contains($query->sql, 'update "products"'))) {
                throw new \RuntimeException('Simulated product synchronization failure during category update');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->patch(route('admin.categories.update', $category), [
                'name' => 'Renamed Category',
                'slug' => 'renamed-category',
                'sort_order' => 2,
                'is_active' => true,
            ]);
        } catch (\Throwable) {
            // Expected failure to trigger rollback
        } finally {
            $fail = false;
        }

        $category->refresh();
        $product->refresh();

        $this->assertEquals('Original Category', $category->name);
        $this->assertEquals('original-category', $category->slug);
        $this->assertEquals('original-category', $product->category);
    }

    /**
     * Test category destroy rollback: if category delete throws, product reassignment rolls back.
     */
    public function test_category_destroy_rolls_back_when_category_deletion_fails(): void
    {
        $category = Category::create([
            'name' => 'Aisle To Delete',
            'slug' => 'aisle-to-delete',
            'icon' => 'Trash',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'aisle-to-delete',
            'category_title' => 'Aisle To Delete',
        ]);

        Category::deleting(function () {
            throw new \RuntimeException('Simulated category delete failure');
        });

        try {
            $this->actingAs($this->admin, 'admin')->delete(route('admin.categories.destroy', $category));
        } catch (\Throwable) {
            // Expected failure to trigger rollback
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $product->refresh();
        $this->assertEquals('aisle-to-delete', $product->category);
        $this->assertNotEquals('grocery', $product->category);
    }

    /**
     * Test recipe kit store rollback: if product sync throws, recipe kit creation rolls back.
     */
    public function test_recipe_kit_store_rolls_back_when_product_sync_fails(): void
    {
        $product = Product::factory()->create();

        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'recipe_kit_products')) {
                throw new \RuntimeException('Simulated pivot sync failure on recipe kit store');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->post(route('admin.recipe-kits.store'), [
                'name' => 'Atomic Rollback Kit',
                'subtitle_tag' => 'ATOMIC TEST',
                'description' => 'A delicious atomic recipe kit for testing rollback.',
                'cooking_time' => '15 mins',
                'servings' => 'Serves 2',
                'difficulty' => 'Easy',
                'price' => 19.99,
                'is_active' => true,
                'products' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Expected failure to trigger rollback
        } finally {
            $fail = false;
        }

        $this->assertDatabaseMissing('recipe_kits', ['name' => 'Atomic Rollback Kit']);
    }

    /**
     * Test recipe kit update rollback: if product sync throws, recipe kit update rolls back.
     */
    public function test_recipe_kit_update_rolls_back_when_product_sync_fails(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $kit = RecipeKit::create([
            'name' => 'Stable Kit',
            'slug' => 'stable-kit',
            'subtitle_tag' => 'ORIGINAL',
            'description' => 'Original description',
            'cooking_time' => '20 mins',
            'servings' => 'Serves 4',
            'difficulty' => 'Medium',
            'price' => 24.99,
            'is_active' => true,
        ]);
        $kit->products()->sync([$product1->id => ['quantity' => 1]]);

        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'recipe_kit_products')) {
                throw new \RuntimeException('Simulated pivot sync failure on recipe kit update');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->patch(route('admin.recipe-kits.update', $kit), [
                'name' => 'Mutated Kit',
                'subtitle_tag' => 'MUTATED',
                'description' => 'Mutated description',
                'cooking_time' => '30 mins',
                'servings' => 'Serves 6',
                'difficulty' => 'Hard',
                'price' => 39.99,
                'is_active' => true,
                'products' => [
                    [
                        'product_id' => $product2->id,
                        'quantity' => 3,
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Expected failure to trigger rollback
        } finally {
            $fail = false;
        }

        $kit->refresh();
        $this->assertEquals('Stable Kit', $kit->name);
        $this->assertEquals('Original description', $kit->description);
        $this->assertEquals(24.99, (float) $kit->price);
        $this->assertEquals([$product1->id], $kit->products->pluck('id')->all());
    }

    /**
     * Test role store rollback: if permission sync fails, role creation is rolled back.
     */
    public function test_role_store_rolls_back_when_permission_sync_fails(): void
    {
        $fail = true;
        DB::listen(function ($query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'role_has_permissions')) {
                throw new \RuntimeException('Simulated permission sync failure on role store');
            }
        });

        try {
            $this->actingAs($this->admin, 'admin')->post(route('admin.roles.store'), [
                'name' => 'Atomic Auditor',
                'permissions' => ['manage products'],
            ]);
        } catch (\Throwable) {
            // Expected failure to trigger rollback
        } finally {
            $fail = false;
        }

        $this->assertDatabaseMissing('roles', ['name' => 'Atomic Auditor', 'guard_name' => 'admin']);
    }

    /**
     * Test order cancellation atomicity on success: status and refunded_total are updated together.
     */
    public function test_order_cancellation_atomically_updates_status_and_refunded_total(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 75.50,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 2],
        ]);

        $result = $order->cancelAndRestock('Test cancellation', (float) $order->total);

        $this->assertTrue($result);

        $order->refresh();
        $product->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals(75.50, (float) $order->refunded_total);
        $this->assertEquals(12, $product->stock);
    }

    /**
     * Test order cancellation rollback: if a downstream exception occurs, stock and status roll back.
     */
    public function test_order_cancellation_rolls_back_if_exception_occurs_in_transaction(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 100.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 4],
        ]);

        try {
            DB::transaction(function () use ($order) {
                $order->cancelAndRestock('Simulated failure', (float) $order->total);
                throw new \RuntimeException('Simulated unexpected failure during cancellation transaction');
            });
        } catch (\Throwable) {
            // Expected failure
        }

        $order->refresh();
        $product->refresh();

        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals(0.00, (float) $order->refunded_total);
        $this->assertEquals(10, $product->stock);
    }

    /**
     * Test account orders query is paginated (10 per page) and supports loading more pages.
     */
    public function test_account_orders_query_is_paginated_and_loads_more_pages(): void
    {
        $user = User::factory()->create();

        // Create 25 orders spanning 25 days
        for ($i = 1; $i <= 25; $i++) {
            Order::factory()->create([
                'user_id' => $user->id,
                'order_number' => sprintf('ORD-TEST-%03d', $i),
                'created_at' => Carbon::now()->subDays(30 - $i),
            ]);
        }

        $this->actingAs($user)->get(route('account'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->has('dbOrders', 10)
                ->where('dbOrders.0.id', 'ORD-TEST-025')
                ->where('ordersPagination.current_page', 1)
                ->where('ordersPagination.last_page', 3)
                ->where('ordersPagination.total', 25)
                ->where('ordersPagination.has_more', true)
            );

        $this->actingAs($user)->get(route('account', ['page' => 2]))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->has('dbOrders', 10)
                ->where('dbOrders.0.id', 'ORD-TEST-015')
                ->where('ordersPagination.current_page', 2)
                ->where('ordersPagination.has_more', true)
            );
    }

    /**
     * Test composite performance indexes exist on orders table.
     */
    public function test_orders_table_has_composite_performance_indexes(): void
    {
        $this->assertTrue($this->hasIndexOnOrders(['status', 'created_at']), 'Missing composite index on orders(status, created_at)');
        $this->assertTrue($this->hasIndexOnOrders(['user_id', 'created_at']), 'Missing composite index on orders(user_id, created_at)');
    }

    /**
     * Test preventLazyLoading is active in non-production environments.
     */
    public function test_prevent_lazy_loading_is_active_in_non_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading(), 'Model::preventsLazyLoading() must be true in testing environment.');

        Order::factory()->count(2)->create();

        $orders = Order::take(2)->get();

        $this->expectException(LazyLoadingViolationException::class);
        // Attempting to access unloaded items relationship on hydrated collection must throw LazyLoadingViolationException
        $items = $orders->first()->items;
    }

    /**
     * Test orders performance indexes migration can rollback and reapply cleanly without throwing QueryException.
     */
    public function test_orders_performance_indexes_migration_can_rollback_and_remigrate(): void
    {
        $migration = require database_path('migrations/2026_10_21_132434_add_status_and_user_indexes_to_orders_table.php');

        // Verify initial state: composite indexes exist
        $this->assertTrue($this->hasIndexOnOrders(['status', 'created_at']));
        $this->assertTrue($this->hasIndexOnOrders(['user_id', 'created_at']));

        // Step 1: Execute down() cleanly without throwing QueryException
        $migration->down();
        $this->assertFalse($this->hasIndexOnOrders(['status', 'created_at']));
        $this->assertFalse($this->hasIndexOnOrders(['user_id', 'created_at']));

        // Step 2: Execute up() cleanly to restore composite indexes
        $migration->up();
        $this->assertTrue($this->hasIndexOnOrders(['status', 'created_at']));
        $this->assertTrue($this->hasIndexOnOrders(['user_id', 'created_at']));
    }

    /**
     * Test Artisan migrate:rollback and migrate commands succeed cleanly for orders indexes.
     */
    public function test_artisan_migrate_rollback_and_remigrate_succeeds_for_orders_indexes(): void
    {
        $exitCode = Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertSame(0, $exitCode);

        $this->assertFalse($this->hasIndexOnOrders(['status', 'created_at']));
        $this->assertFalse($this->hasIndexOnOrders(['user_id', 'created_at']));

        $remigrateCode = Artisan::call('migrate');
        $this->assertSame(0, $remigrateCode);

        $this->assertTrue($this->hasIndexOnOrders(['status', 'created_at']));
        $this->assertTrue($this->hasIndexOnOrders(['user_id', 'created_at']));
    }

    /**
     * Helper to verify if an index with given columns exists on the orders table.
     *
     * @param  array<int, string>  $columns
     */
    private function hasIndexOnOrders(array $columns): bool
    {
        foreach (Schema::getIndexes('orders') as $index) {
            if (($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }
}
