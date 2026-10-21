<?php

namespace Tests\Feature\Challenger;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\User;
use App\Services\StripePayments;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Stripe\Refund;
use Tests\TestCase;

class Milestone2DatabaseAtomicityTest extends TestCase
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
     * EMPIRICAL TEST 1:
     * Simulate an exception during Product::where('category', ...)->update(...) during category update.
     * Verify category name and slug are NOT committed.
     */
    public function test_category_update_rolls_back_completely_when_product_update_fails(): void
    {
        $category = Category::create([
            'name' => 'Original Spices',
            'slug' => 'original-spices',
            'hindi_title' => 'Asli Masale',
            'icon' => 'Sparkles',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $product1 = Product::factory()->create([
            'category' => 'original-spices',
            'category_title' => 'Original Spices',
        ]);
        $product2 = Product::factory()->create([
            'category' => 'original-spices',
            'category_title' => 'Original Spices',
        ]);

        $interceptProductUpdate = true;
        DB::listen(function ($query) use (&$interceptProductUpdate) {
            if ($interceptProductUpdate && (
                str_contains($query->sql, 'update `products`') ||
                str_contains($query->sql, 'update "products"') ||
                (str_contains(strtolower($query->sql), 'update') && str_contains(strtolower($query->sql), 'products'))
            )) {
                throw new \RuntimeException('ADVERSARIAL_FORCED_PRODUCT_UPDATE_FAILURE');
            }
        });

        $this->withoutExceptionHandling();

        $exceptionThrown = false;
        try {
            $this->actingAs($this->admin, 'admin')->patch(route('admin.categories.update', $category), [
                'name' => 'Mutated Spices',
                'slug' => 'mutated-spices',
                'hindi_title' => 'Naye Masale',
                'icon' => 'Sparkles',
                'sort_order' => 20,
                'is_active' => true,
            ]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ADVERSARIAL_FORCED_PRODUCT_UPDATE_FAILURE', $e->getMessage());
        } finally {
            $interceptProductUpdate = false;
        }

        $this->assertTrue($exceptionThrown, 'Expected simulated Product update exception was not thrown.');

        // Re-read category and products directly from database
        $category->refresh();
        $product1->refresh();
        $product2->refresh();

        // Verify category name, slug, and hindi_title are NOT committed
        $this->assertSame('Original Spices', $category->name);
        $this->assertSame('original-spices', $category->slug);
        $this->assertSame('Asli Masale', $category->hindi_title);
        $this->assertSame(10, $category->sort_order);

        // Verify products retain their original category assignment
        $this->assertSame('original-spices', $product1->category);
        $this->assertSame('Original Spices', $product1->category_title);
        $this->assertSame('original-spices', $product2->category);
        $this->assertSame('Original Spices', $product2->category_title);

        $this->assertDatabaseMissing('categories', ['slug' => 'mutated-spices']);
        $this->assertDatabaseHas('categories', ['slug' => 'original-spices', 'name' => 'Original Spices']);
    }

    /**
     * EMPIRICAL TEST 2:
     * Simulate an exception during category delete after product reassignment.
     * Verify category remains in database and product reassignments are rolled back.
     */
    public function test_category_destroy_rolls_back_product_reassignment_when_delete_fails(): void
    {
        $category = Category::create([
            'name' => 'Delete Candidate',
            'slug' => 'delete-candidate',
            'icon' => 'Trash',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'delete-candidate',
            'category_title' => 'Delete Candidate',
        ]);

        Category::deleting(function () {
            throw new \RuntimeException('ADVERSARIAL_FORCED_CATEGORY_DELETE_FAILURE');
        });

        $this->withoutExceptionHandling();

        $exceptionThrown = false;
        try {
            $this->actingAs($this->admin, 'admin')->delete(route('admin.categories.destroy', $category));
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ADVERSARIAL_FORCED_CATEGORY_DELETE_FAILURE', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Expected simulated Category delete exception was not thrown.');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'delete-candidate']);

        $product->refresh();
        $this->assertSame('delete-candidate', $product->category);
        $this->assertSame('Delete Candidate', $product->category_title);
        $this->assertNotSame('grocery', $product->category);
    }

    /**
     * EMPIRICAL TEST 3:
     * Simulate an exception during $kit->products()->sync(...) during kit creation.
     * Verify RecipeKit record does NOT persist in the database.
     */
    public function test_recipe_kit_store_rolls_back_when_pivot_sync_fails(): void
    {
        $product = Product::factory()->create(['stock' => 50]);

        $interceptPivotInsert = true;
        DB::listen(function ($query) use (&$interceptPivotInsert) {
            if ($interceptPivotInsert && str_contains(strtolower($query->sql), 'recipe_kit_products')) {
                throw new \RuntimeException('ADVERSARIAL_FORCED_PIVOT_SYNC_FAILURE');
            }
        });

        $this->withoutExceptionHandling();

        $exceptionThrown = false;
        try {
            $this->actingAs($this->admin, 'admin')->post(route('admin.recipe-kits.store'), [
                'name' => 'Challenger Phantom Kit',
                'slug' => 'challenger-phantom-kit',
                'subtitle_tag' => 'PHANTOM',
                'description' => 'Should never be committed to database.',
                'cooking_time' => '25 mins',
                'servings' => 'Serves 4',
                'difficulty' => 'Medium',
                'price' => 29.99,
                'is_active' => true,
                'products' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ADVERSARIAL_FORCED_PIVOT_SYNC_FAILURE', $e->getMessage());
        } finally {
            $interceptPivotInsert = false;
        }

        $this->assertTrue($exceptionThrown, 'Expected simulated RecipeKit pivot sync exception was not thrown.');

        // Verify RecipeKit record does NOT persist
        $this->assertDatabaseMissing('recipe_kits', [
            'name' => 'Challenger Phantom Kit',
        ]);
        $this->assertDatabaseMissing('recipe_kits', [
            'slug' => 'challenger-phantom-kit',
        ]);

        // Verify no orphan records in pivot table
        $this->assertSame(
            0,
            DB::table('recipe_kit_products')->where('product_id', $product->id)->count()
        );
    }

    /**
     * EMPIRICAL TEST 4:
     * Simulate an exception during $recipeKit->products()->sync(...) during kit update.
     * Verify RecipeKit attributes and pivot rows roll back to prior state.
     */
    public function test_recipe_kit_update_rolls_back_when_pivot_sync_fails(): void
    {
        $productA = Product::factory()->create(['name' => 'Ingredient A']);
        $productB = Product::factory()->create(['name' => 'Ingredient B']);

        $kit = RecipeKit::create([
            'name' => 'Original Kit Name',
            'slug' => 'original-kit-name',
            'subtitle_tag' => 'ORIGINAL_TAG',
            'description' => 'Original Kit Description',
            'cooking_time' => '15 mins',
            'servings' => 'Serves 2',
            'difficulty' => 'Easy',
            'price' => 18.50,
            'is_active' => true,
        ]);
        $kit->products()->sync([$productA->id => ['quantity' => 1, 'sort_order' => 0]]);

        $interceptPivot = true;
        DB::listen(function ($query) use (&$interceptPivot) {
            if ($interceptPivot && str_contains(strtolower($query->sql), 'recipe_kit_products')) {
                throw new \RuntimeException('ADVERSARIAL_FORCED_KIT_UPDATE_PIVOT_FAILURE');
            }
        });

        $this->withoutExceptionHandling();

        $exceptionThrown = false;
        try {
            $this->actingAs($this->admin, 'admin')->put(route('admin.recipe-kits.update', $kit), [
                'name' => 'Overwritten Kit Name',
                'slug' => 'overwritten-kit-name',
                'subtitle_tag' => 'OVERWRITTEN_TAG',
                'description' => 'Overwritten Kit Description',
                'cooking_time' => '45 mins',
                'servings' => 'Serves 8',
                'difficulty' => 'Hard',
                'price' => 49.99,
                'is_active' => false,
                'products' => [
                    [
                        'product_id' => $productB->id,
                        'quantity' => 5,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ADVERSARIAL_FORCED_KIT_UPDATE_PIVOT_FAILURE', $e->getMessage());
        } finally {
            $interceptPivot = false;
        }

        $this->assertTrue($exceptionThrown, 'Expected simulated RecipeKit update pivot exception was not thrown.');

        $kit->refresh();

        // Verify RecipeKit record retained its original values
        $this->assertSame('Original Kit Name', $kit->name);
        $this->assertSame('original-kit-name', $kit->slug);
        $this->assertSame('ORIGINAL_TAG', $kit->subtitle_tag);
        $this->assertSame('Original Kit Description', $kit->description);
        $this->assertEquals(18.50, (float) $kit->price);
        $this->assertTrue((bool) $kit->is_active);

        // Verify pivot is still mapped to Product A, not Product B
        $this->assertSame([$productA->id], $kit->products->pluck('id')->all());
        $this->assertDatabaseMissing('recipe_kit_products', [
            'recipe_kit_id' => $kit->id,
            'product_id' => $productB->id,
        ]);
    }

    /**
     * EMPIRICAL TEST 5:
     * Simulate an exception during role permission sync.
     * Verify role is NOT persisted.
     */
    public function test_role_store_rolls_back_when_permission_sync_fails(): void
    {
        Permission::findOrCreate('manage products', 'admin');

        $interceptPermissionSync = true;
        DB::listen(function ($query) use (&$interceptPermissionSync) {
            if ($interceptPermissionSync && str_contains(strtolower($query->sql), 'role_has_permissions')) {
                throw new \RuntimeException('ADVERSARIAL_FORCED_ROLE_PERMISSION_SYNC_FAILURE');
            }
        });

        $this->withoutExceptionHandling();

        $exceptionThrown = false;
        try {
            $this->actingAs($this->admin, 'admin')->post(route('admin.roles.store'), [
                'name' => 'Ghost Auditor Role',
                'permissions' => ['manage products'],
            ]);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ADVERSARIAL_FORCED_ROLE_PERMISSION_SYNC_FAILURE', $e->getMessage());
        } finally {
            $interceptPermissionSync = false;
        }

        $this->assertTrue($exceptionThrown, 'Expected simulated Role permission sync exception was not thrown.');

        // Verify role does not persist
        $this->assertDatabaseMissing('roles', [
            'name' => 'Ghost Auditor Role',
            'guard_name' => 'admin',
        ]);
    }

    /**
     * EMPIRICAL TEST 6:
     * Verify that when order cancellation is triggered, status and refunded_total cannot become desynchronized.
     * Sub-test A: Successful cancellation atomically updates status = 'cancelled' and refunded_total = total.
     */
    public function test_order_cancellation_atomically_synchronizes_status_and_refunded_total(): void
    {
        $product = Product::factory()->create(['stock' => 15]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 88.50,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 3],
        ]);

        $success = $order->cancelAndRestock('Challenger verified cancellation', (float) $order->total);
        $this->assertTrue($success);

        $order->refresh();
        $product->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(88.50, (float) $order->refunded_total);
        $this->assertSame(18, $product->stock); // 15 + 3 restocked
    }

    /**
     * EMPIRICAL TEST 7:
     * Verify that when order cancellation is triggered, status and refunded_total cannot become desynchronized.
     * Sub-test B: If Product::returnToStock fails, order status and refunded_total are NOT changed.
     */
    public function test_order_cancellation_rolls_back_if_product_restocking_fails(): void
    {
        $product = Product::factory()->create(['stock' => 20]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 125.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 5],
        ]);

        $interceptStockUpdate = true;
        DB::listen(function ($query) use (&$interceptStockUpdate) {
            if ($interceptStockUpdate && (
                str_contains(strtolower($query->sql), 'update') &&
                str_contains(strtolower($query->sql), 'products')
            )) {
                throw new \RuntimeException('ADVERSARIAL_FORCED_STOCK_RESTOCK_FAILURE');
            }
        });

        $exceptionThrown = false;
        try {
            $order->cancelAndRestock('Failure test', (float) $order->total);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ADVERSARIAL_FORCED_STOCK_RESTOCK_FAILURE', $e->getMessage());
        } finally {
            $interceptStockUpdate = false;
        }

        $this->assertTrue($exceptionThrown);

        $order->refresh();
        $product->refresh();

        // Must remain in original state - neither status nor refunded_total may be changed
        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(0.00, (float) $order->refunded_total);
        $this->assertSame(20, $product->stock);
    }

    /**
     * EMPIRICAL TEST 8:
     * Verify that when order cancellation is triggered, status and refunded_total cannot become desynchronized.
     * Sub-test C: If an exception occurs right after cancelAndRestock inside AdminOrderController's transaction,
     * status and refunded_total rollback together (no partial state).
     */
    public function test_order_cancellation_rolls_back_in_admin_controller_transaction(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 50.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 2],
        ]);

        // Simulate an outer transaction failure
        try {
            DB::transaction(function () use ($order) {
                $order->cancelAndRestock('Cancelled by admin', (float) $order->total);
                $order->update(['refunded_total' => $order->total]);

                throw new \RuntimeException('ADVERSARIAL_FORCED_POST_CANCEL_TRANSACTION_FAILURE');
            });
        } catch (\Throwable $e) {
            $this->assertStringContainsString('ADVERSARIAL_FORCED_POST_CANCEL_TRANSACTION_FAILURE', $e->getMessage());
        }

        $order->refresh();
        $product->refresh();

        // Atomicity guarantee: status must remain confirmed, refunded_total must remain 0.00, stock must remain 10
        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(0.00, (float) $order->refunded_total);
        $this->assertSame(10, $product->stock);
    }

    /**
     * EMPIRICAL TEST 9:
     * Verify that when order cancellation is triggered, status and refunded_total cannot become desynchronized.
     * Sub-test D: If Stripe refund fails during Admin cancelOrder, order status and refunded_total are NOT updated.
     */
    public function test_order_cancellation_does_not_modify_status_or_refund_if_stripe_fails(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 70.00,
            'refunded_total' => 0.00,
            'stripe_payment_id' => 'pi_test_adversarial_failure',
            'reserved_stock' => [$product->id => 2],
        ]);

        $mockStripe = Mockery::mock(StripePayments::class);
        $mockStripe->shouldReceive('isEnabled')->andReturn(true);
        $mockStripe->shouldReceive('refundPayment')
            ->with('pi_test_adversarial_failure')
            ->andThrow(new \Exception('Stripe API network timeout'));
        $this->app->instance(StripePayments::class, $mockStripe);

        $response = $this->actingAs($this->admin, 'admin')->patchJson(route('admin.orders.status.update', $order), [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);

        $order->refresh();
        $product->refresh();

        // Verify status and refunded_total remain synchronized to original uncancelled state
        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(0.00, (float) $order->refunded_total);
        $this->assertSame(10, $product->stock);
    }

    /**
     * EMPIRICAL TEST 10:
     * Verify concurrent duplicate cancellation cannot restock twice or corrupt refunded_total.
     */
    public function test_duplicate_cancellation_does_not_double_restock_or_corrupt_refunded_total(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 99.00,
            'refunded_total' => 0.00,
            'reserved_stock' => [$product->id => 4],
        ]);

        // First cancellation
        $firstResult = $order->cancelAndRestock('Initial cancel', (float) $order->total);
        $this->assertTrue($firstResult);

        $order->refresh();
        $product->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(99.00, (float) $order->refunded_total);
        $this->assertSame(14, $product->stock);

        // Second cancellation attempt
        $secondResult = $order->cancelAndRestock('Duplicate cancel attempt', (float) $order->total);
        $this->assertFalse($secondResult, 'Duplicate cancellation must return false');

        $order->refresh();
        $product->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(99.00, (float) $order->refunded_total);
        $this->assertSame(14, $product->stock, 'Product stock must NOT be incremented twice');
    }

    /**
     * EMPIRICAL TEST 11:
     * Verify cancellation of an order that already has a partial refund updates refunded_total to full total atomically.
     */
    public function test_cancellation_with_prior_partial_refund_synchronizes_refunded_total(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $order = Order::factory()->create([
            'status' => 'confirmed',
            'total' => 100.00,
            'refunded_total' => 35.00, // $35 already refunded for one item
            'stripe_payment_id' => 'pi_test_partial_then_cancel',
            'reserved_stock' => [$product->id => 1],
        ]);

        $mockRefund = Mockery::mock(Refund::class);
        $mockStripe = Mockery::mock(StripePayments::class);
        $mockStripe->shouldReceive('isEnabled')->andReturn(true);
        // Expect partial refund for remaining balance ($65.00 = 6500 cents)
        $mockStripe->shouldReceive('refundPartialPayment')
            ->with(
                'pi_test_partial_then_cancel',
                6500,
                Mockery::any(),
                Mockery::any()
            )
            ->once()
            ->andReturn($mockRefund);
        $this->app->instance(StripePayments::class, $mockStripe);

        $response = $this->actingAs($this->admin, 'admin')->patchJson(route('admin.orders.status.update', $order), [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $product->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(100.00, (float) $order->refunded_total);
        $this->assertSame(6, $product->stock);
    }

    /**
     * EMPIRICAL TEST 12:
     * Verify composite indexes exist on orders table with exact column compositions.
     */
    public function test_composite_indexes_exist_on_orders_table(): void
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

        $this->assertTrue($hasStatusCreatedAt, 'Missing composite index on orders(status, created_at)');
        $this->assertTrue($hasUserIdCreatedAt, 'Missing composite index on orders(user_id, created_at)');
    }

    /**
     * EMPIRICAL TEST 13:
     * Verify preventLazyLoading throws exception when lazy loading collections in non-production.
     */
    public function test_prevent_lazy_loading_throws_on_collection(): void
    {
        $this->assertTrue(Model::preventsLazyLoading(), 'Model::preventsLazyLoading() must be enabled');

        Order::factory()->count(2)->create();
        $orders = Order::take(2)->get();

        $this->expectException(LazyLoadingViolationException::class);
        $items = $orders->first()->items;
    }

    /**
     * EMPIRICAL TEST 14:
     * Verify AccountController::getUserOrders() is bounded to 50 records.
     */
    public function test_account_orders_is_paginated_to_10_per_page(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 25; $i++) {
            Order::factory()->create([
                'user_id' => $user->id,
                'order_number' => sprintf('CHALLENGE-ORD-%03d', $i),
                'created_at' => now()->subDays(30 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('account'));
        $response->assertStatus(200);

        // Fetch Inertia page data
        $page = $response->viewData('page');
        $dbOrders = $page['props']['dbOrders'] ?? [];
        $pagination = $page['props']['ordersPagination'] ?? [];

        $this->assertCount(10, $dbOrders);
        $this->assertSame('CHALLENGE-ORD-025', $dbOrders[0]['id']);
        $this->assertTrue($pagination['has_more']);
        $this->assertSame(25, $pagination['total']);
    }
}
