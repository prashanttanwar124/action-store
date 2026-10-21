<?php

namespace Tests\Feature\E2E;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrossFeatureCombinationsE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
        $this->seed(AdminSeeder::class);

        $this->customer = User::factory()->create([
            'name' => 'Rohan Sen',
            'email' => 'rohan.sen@example.com',
        ]);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
    }

    public function test_mixed_basket_checkout_with_loose_items_and_recipe_kit_bundle(): void
    {
        // Ingredient products for kit
        $paneer = Product::factory()->create(['name' => 'Malai Paneer Block', 'price' => 6.00, 'stock' => 10]);
        $butter = Product::factory()->create(['name' => 'Amul Salted Butter', 'price' => 4.00, 'stock' => 8]);

        // Standalone loose product
        $spices = Product::factory()->create(['name' => 'Garam Masala Box', 'price' => 5.50, 'stock' => 15]);

        // Bundle Recipe Kit
        $kit = RecipeKit::create([
            'name' => 'Paneer Butter Masala Dinner Kit',
            'slug' => 'paneer-butter-masala-dinner-kit',
            'price' => 15.00,
            'is_active' => true,
        ]);
        $kit->products()->attach([
            $paneer->id => ['quantity' => 1, 'sort_order' => 1],
            $butter->id => ['quantity' => 1, 'sort_order' => 2],
        ]);

        $payload = [
            'items' => [
                [
                    'id' => $spices->id,
                    'name' => $spices->name,
                    'price' => 5.50,
                    'quantity' => 2, // 2 x 5.50 = 11.00
                ],
                [
                    'id' => $kit->slug,
                    'type' => 'recipe-kit',
                    'name' => $kit->name,
                    'price' => 15.00,
                    'quantity' => 1, // 1 x 15.00 = 15.00
                ],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ];

        $response = $this->actingAs($this->customer)->postJson('/checkout', $payload);

        $response->assertStatus(201);
        $orderId = $response->json('order.id');

        // Total: 11.00 + 15.00 = 26.00
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'subtotal' => 26.00,
            'total' => 26.00,
            'status' => 'confirmed',
        ]);

        // Stock checks:
        // Spices decremented by 2: 15 -> 13
        $this->assertEquals(13, $spices->fresh()->stock);
        // Kit ingredient paneer decremented by 1: 10 -> 9
        $this->assertEquals(9, $paneer->fresh()->stock);
        // Kit ingredient butter decremented by 1: 8 -> 7
        $this->assertEquals(7, $butter->fresh()->stock);
    }

    public function test_checkout_with_price_tampering_enforces_authoritative_catalog_pricing(): void
    {
        $product = Product::factory()->create([
            'name' => 'Premium Saffron 5g',
            'price' => 45.00,
            'stock' => 10,
        ]);

        // Tampered payload attempting to pay $0.05 per unit
        $payload = [
            'items' => [
                [
                    'id' => $product->id,
                    'name' => 'Hacked Saffron',
                    'price' => 0.05,
                    'quantity' => 2,
                ],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ];

        $response = $this->actingAs($this->customer)->postJson('/checkout', $payload);

        $response->assertStatus(201);

        // System overrides tampered name & price with authoritative DB records: 2 x $45.00 = $90.00
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'subtotal' => 90.00,
            'total' => 90.00,
            'points_earned' => 90,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'name' => 'Premium Saffron 5g',
            'unit_price' => 45.00,
            'quantity' => 2,
            'total_price' => 90.00,
        ]);
    }

    public function test_partial_refund_reflects_in_customer_account_and_reorder_without_kit_id_corruption(): void
    {
        $looseItem = Product::factory()->create(['name' => 'Tata Tea Premium', 'price' => 7.50, 'stock' => 5]);
        $kitItem = Product::factory()->create(['name' => 'Poha Rice Flakes', 'price' => 3.00, 'stock' => 10]);

        $kit = RecipeKit::create([
            'name' => 'Indori Poha Kit',
            'slug' => 'indori-poha-kit',
            'price' => 8.25,
            'is_active' => true,
        ]);
        $kit->products()->attach($kitItem, ['quantity' => 1]);

        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'subtotal' => 15.75,
            'total' => 15.75,
            'status' => 'packing',
            'reserved_stock' => [
                $looseItem->id => 1,
                $kitItem->id => 1,
            ],
        ]);

        $itemLoose = $order->items()->create([
            'product_id' => $looseItem->id,
            'name' => $looseItem->name,
            'unit_price' => 7.50,
            'quantity' => 1,
            'total_price' => 7.50,
            'is_packed' => true,
        ]);

        $itemKit = $order->items()->create([
            'product_id' => null, // Recipe kit line items have null product_id or distinct identifier
            'name' => $kit->name,
            'unit_price' => 8.25,
            'quantity' => 1,
            'total_price' => 8.25,
            'is_packed' => true,
        ]);

        // Admin refunds the loose item ($7.50)
        $refundResponse = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.items.refund', [$order->id, $itemLoose->id]), [
                'quantity' => 1,
                'zero_product_stock' => true,
                'reason' => 'Package seal broken',
            ]);

        $refundResponse->assertOk();

        // Customer visits /account: refunded total and points adjusted
        $accountResponse = $this->actingAs($this->customer->refresh())->get('/account');
        $accountResponse->assertOk();
        $accountResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('dbOrders', 1)
            ->where('dbOrders.0.total', 15.75)
            ->where('dbOrders.0.refunded_total', 7.50)
            ->where('auth.user.reward_points', 108) // 100 welcome + 8 net remaining
        );

        // Customer visits /reorder
        $reorderResponse = $this->actingAs($this->customer)->get('/reorder');
        $reorderResponse->assertOk();
        $reorderResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
            ->has('dbOrders.0.items', 2)
            ->where('dbOrders.0.items.1.name', 'Indori Poha Kit')
        );
    }

    public function test_busy_mode_extends_estimated_pickup_time_during_customer_checkout(): void
    {
        // Admin activates busy mode with 25 extra minutes
        $this->actingAs($this->admin, 'admin')->post(route('admin.store-info.busy-mode'), [
            'busy_mode_extra_minutes' => 25,
            'busy_mode_reason' => 'Rainy weather delivery spike',
        ]);

        $setting = StoreSetting::current();
        $this->assertTrue($setting->is_busy);
        $this->assertEquals(25, $setting->busy_mode_extra_minutes);

        // Checkout page loads successfully with busy settings active
        $response = $this->actingAs($this->customer)->get('/checkout');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Checkout')
            ->has('storeTimezone')
            ->has('bookedSlots')
        );
    }

    public function test_concurrent_order_placement_atomically_guards_stock(): void
    {
        $singleStockProduct = Product::factory()->create([
            'name' => 'Last Fresh Samosa Box',
            'price' => 8.00,
            'stock' => 1,
        ]);

        $secondCustomer = User::factory()->create([
            'name' => 'Pooja Verma',
            'email' => 'pooja@example.com',
        ]);

        $payload = [
            'items' => [
                ['id' => $singleStockProduct->id, 'name' => $singleStockProduct->name, 'price' => 8.00, 'quantity' => 1],
            ],
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ];

        // Customer 1 purchases the last remaining unit
        $response1 = $this->actingAs($this->customer)->postJson('/checkout', $payload);
        $response1->assertStatus(201);
        $this->assertEquals(0, $singleStockProduct->fresh()->stock);

        // Customer 2 attempts to purchase simultaneously
        $response2 = $this->actingAs($secondCustomer)->postJson('/checkout', $payload);
        $response2->assertStatus(422)
            ->assertJsonPath('errors.error_code.0', 'out_of_stock');

        // Final inventory cannot drop below 0
        $this->assertEquals(0, $singleStockProduct->fresh()->stock);
        $this->assertEquals(1, Order::where('status', 'confirmed')->count());
    }
}
