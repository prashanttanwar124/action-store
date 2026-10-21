<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_account_page(): void
    {
        $response = $this->get('/account');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'account')
            ->where('auth.user', null)
            ->where('activeOrders', [])
            ->where('rewardLedger', [])
        );
    }

    public function test_authenticated_user_can_view_account_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
        ]);

        $response = $this->actingAs($user)->get('/account');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'account')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'Priya Sharma')
            ->has('rewardLedger', 1)
            ->where('rewardLedger.0.points', 100)
            ->where('rewardLedger.0.description', 'Welcome bonus')
        );
    }

    public function test_account_page_returns_active_orders_and_excludes_completed_or_cancelled(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'MM-ACTIVE-1',
            'status' => 'packing',
            'pickup_slot' => 'Today 4–5 pm',
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'MM-DONE-1',
            'status' => 'completed',
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'MM-CANCEL-1',
            'status' => 'cancelled',
        ]);
        Order::factory()->create([
            'user_id' => $otherUser->id,
            'order_number' => 'MM-OTHER-1',
            'status' => 'ready_for_pickup',
        ]);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->has('activeOrders', 1)
                ->where('activeOrders.0.id', 'MM-ACTIVE-1')
                ->where('activeOrders.0.status', 'packing')
                ->where('activeOrders.0.pickup_slot', 'Today 4–5 pm')
            );
    }

    public function test_authenticated_user_receives_server_calculated_reward_points(): void
    {
        $user = User::factory()->create();

        // 0 orders -> 100 welcome bonus
        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('auth.user.reward_points', 100)
            );

        // Completed order ($65.40) + confirmed order with refund ($30 - $10) + cancelled ($100)
        Order::factory()->create(['user_id' => $user->id, 'total' => 65.40, 'status' => 'completed']);
        Order::factory()->create(['user_id' => $user->id, 'total' => 30.00, 'refunded_total' => 10.00, 'status' => 'confirmed']);
        Order::factory()->create(['user_id' => $user->id, 'total' => 100.00, 'status' => 'cancelled']);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('auth.user.reward_points', 185)
            );
    }

    public function test_reorder_page_can_be_viewed_and_returns_popular_essentials_for_guest(): void
    {
        Product::factory()->create([
            'name' => 'Royal Basmati Rice',
            'slug' => 'royal-basmati-rice',
            'price' => 18.99,
            'stock' => 25,
            'buy_again' => true,
        ]);

        $response = $this->get('/reorder');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
            ->where('buyAgainItems', [])
            ->has('popularEssentials', 1)
            ->where('popularEssentials.0.slug', 'royal-basmati-rice')
        );
    }

    public function test_reorder_page_aggregates_buy_again_items_by_order_frequency(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $paneer = Product::factory()->create([
            'name' => 'Fresh Malai Paneer',
            'slug' => 'fresh-malai-paneer',
            'price' => 5.49,
            'stock' => 12,
        ]);
        $atta = Product::factory()->create([
            'name' => 'Chakki Fresh Atta',
            'slug' => 'chakki-fresh-atta',
            'price' => 14.99,
            'stock' => 8,
        ]);
        $kit = RecipeKit::create([
            'name' => 'Shahi Paneer Kit',
            'slug' => 'shahi-paneer-kit',
            'price' => 16.50,
            'original_price' => 19.00,
            'servings' => 'Serves 4',
            'is_active' => true,
        ]);

        // Order 1: Paneer + Atta
        $order1 = Order::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $paneer->id,
            'name' => $paneer->name,
            'unit_price' => 5.00,
            'quantity' => 2,
            'total_price' => 10.00,
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $atta->id,
            'name' => $atta->name,
            'unit_price' => 14.00,
            'quantity' => 1,
            'total_price' => 14.00,
        ]);

        // Order 2: Paneer again + Recipe Kit
        $order2 = Order::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $paneer->id,
            'name' => $paneer->name,
            'unit_price' => 5.49,
            'quantity' => 1,
            'total_price' => 5.49,
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'recipe_kit_id' => $kit->id,
            'name' => $kit->name,
            'unit_price' => 16.50,
            'quantity' => 1,
            'total_price' => 16.50,
        ]);

        // Other user's order should never leak
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id, 'status' => 'completed']);
        OrderItem::create([
            'order_id' => $otherOrder->id,
            'product_id' => $atta->id,
            'name' => $atta->name,
            'unit_price' => 14.99,
            'quantity' => 10,
            'total_price' => 149.90,
        ]);

        $this->actingAs($user)->get('/reorder')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('activeSection', 'reorder')
                ->has('buyAgainItems', 3)
                // Paneer ordered 2 times -> first
                ->where('buyAgainItems.0.id', $paneer->id)
                ->where('buyAgainItems.0.times_ordered', 2)
                ->where('buyAgainItems.0.total_quantity', 3)
                ->where('buyAgainItems.0.price', 5.49)
                ->where('popularEssentials', [])
            );
    }

    public function test_dashboard_redirects_to_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('account'));
    }
}
