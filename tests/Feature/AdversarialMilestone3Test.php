<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdversarialMilestone3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    /**
     * Scenario 1: User with 0 orders must receive exactly 100 points (welcome bonus).
     */
    public function test_reward_points_zero_orders_equals_one_hundred(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $user->orders()->count());
        $this->assertSame(100, $user->refreshRewardPointsBalance());
        $this->assertSame(100, $user->reward_points);
    }

    /**
     * Scenario 2: User with orders 45.99, 12.50, 0.00 -> 100 + 45 + 12 = 157.
     */
    public function test_reward_points_fractional_totals_floors_per_order(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 45.99,
            'refunded_total' => 0.00,
            'status' => 'completed',
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 12.50,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 0.00,
            'refunded_total' => 0.00,
            'status' => 'completed',
        ]);

        $this->assertSame(157, $user->refreshRewardPointsBalance());
        $this->assertSame(157, $user->fresh()->reward_points);
    }

    /**
     * Scenario 3: User with refunded orders: total 100, refunded 40 -> 100 + 60 = 160.
     */
    public function test_reward_points_deducts_refunded_total(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 100.00,
            'refunded_total' => 40.00,
            'status' => 'completed',
        ]);

        $this->assertSame(160, $user->refreshRewardPointsBalance());
        $this->assertSame(160, $user->fresh()->reward_points);
    }

    /**
     * Scenario 4: User with over-refunded or negative net: floor(max(0, net)) ensures points never drop below 100.
     */
    public function test_reward_points_never_drops_below_welcome_bonus_on_negative_net(): void
    {
        $user = User::factory()->create();

        // Single order where refunded > total
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 50.00,
            'refunded_total' => 70.00,
            'status' => 'completed',
        ]);

        $this->assertSame(100, $user->refreshRewardPointsBalance());
        $this->assertSame(100, $user->fresh()->reward_points);

        // Multiple orders resulting in overall negative net
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 10.00,
            'refunded_total' => 30.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(100, $user->refreshRewardPointsBalance());
        $this->assertSame(100, $user->fresh()->reward_points);
    }

    /**
     * Scenario 5: User with cancelled orders: must not contribute to points.
     */
    public function test_reward_points_ignores_cancelled_orders(): void
    {
        $user = User::factory()->create();

        // Cancelled order of $500
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 500.00,
            'refunded_total' => 0.00,
            'status' => 'cancelled',
        ]);

        // Cancelled order with partial refund
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 150.00,
            'refunded_total' => 50.00,
            'status' => 'cancelled',
        ]);

        // Points should remain base 100
        $this->assertSame(100, $user->refreshRewardPointsBalance());

        // Valid order of $40 should add 40 points
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 40.00,
            'refunded_total' => 0.00,
            'status' => 'completed',
        ]);

        $this->assertSame(140, $user->refreshRewardPointsBalance());
    }

    /**
     * Scenario 6: Mixed active statuses (pending, confirmed, preparing, ready, completed) all contribute.
     */
    public function test_reward_points_includes_all_non_cancelled_statuses(): void
    {
        $user = User::factory()->create();

        Order::factory()->create(['user_id' => $user->id, 'total' => 20.00, 'refunded_total' => 0, 'status' => 'pending']);
        Order::factory()->create(['user_id' => $user->id, 'total' => 25.00, 'refunded_total' => 0, 'status' => 'confirmed']);
        Order::factory()->create(['user_id' => $user->id, 'total' => 30.00, 'refunded_total' => 0, 'status' => 'preparing']);
        Order::factory()->create(['user_id' => $user->id, 'total' => 15.00, 'refunded_total' => 0, 'status' => 'ready']);
        Order::factory()->create(['user_id' => $user->id, 'total' => 10.00, 'refunded_total' => 0, 'status' => 'completed']);

        // Net = 20 + 25 + 30 + 15 + 10 = 100
        // Points = 100 + 100 = 200
        $this->assertSame(200, $user->refreshRewardPointsBalance());
    }

    /**
     * Scenario 7: Multi-user tenant isolation: Order totals must not leak across users.
     */
    public function test_reward_points_tenant_isolation_between_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Order::factory()->create(['user_id' => $userA->id, 'total' => 300.00, 'refunded_total' => 0, 'status' => 'completed']);
        Order::factory()->create(['user_id' => $userB->id, 'total' => 45.00, 'refunded_total' => 5, 'status' => 'completed']);

        $this->assertSame(400, $userA->refreshRewardPointsBalance());
        $this->assertSame(140, $userB->refreshRewardPointsBalance());
    }

    /**
     * Scenario 8: Default refunded_total (0.00) is handled cleanly without requiring explicit value.
     */
    public function test_reward_points_default_refunded_total_calculates_correctly(): void
    {
        $user = User::factory()->create();

        // Order created without specifying refunded_total (relies on DB default 0.00)
        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 50.00,
            'status' => 'completed',
        ]);

        $this->assertSame(150, $user->refreshRewardPointsBalance());
    }

    /**
     * Scenario 9: Inertia response for /account as authenticated customer.
     * auth.user.reward_points must be present, correct, and not dirty the underlying user model.
     */
    public function test_inertia_account_response_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Aditi Rao',
            'email' => 'aditi@example.com',
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'total' => 75.80,
            'refunded_total' => 15.30,
            'status' => 'completed',
        ]);
        // 75.80 - 15.30 = 60.50 -> floor(60.50) = 60 -> 100 + 60 = 160

        $response = $this->actingAs($user)->get('/account');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', 'aditi@example.com')
            ->where('auth.user.reward_points', 160)
            ->has('dbOrders', 1)
            ->where('dbOrders.0.total', 75.8)
            ->where('dbOrders.0.refunded_total', 15.3)
        );

        // Verify request user model in DB has no unsaved changes or unknown columns
        $this->assertEmpty($user->getDirty());
    }

    /**
     * Scenario 10: Inertia response for /account as guest.
     * auth.user must be strictly null (no object, no empty array, no 0).
     */
    public function test_inertia_account_response_for_guest_user(): void
    {
        // Ensure there are users and orders in the DB
        $otherUser = User::factory()->create();
        Order::factory()->create(['user_id' => $otherUser->id, 'total' => 120.00]);

        $response = $this->get('/account');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('auth.user', null)
            ->where('dbOrders', [])
        );

        // Explicitly assert that auth.user is strictly null in the page props array
        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('auth', $props);
        $this->assertNull($props['auth']['user'], 'auth.user must be strictly null for guests');
        $this->assertSame([], $props['dbOrders'], 'dbOrders must be an empty array for guests');
    }

    /**
     * Scenario 11: Admin guard isolation: Admin logged in does not leak into customer auth.user on /account.
     */
    public function test_admin_auth_does_not_leak_into_customer_auth_user_on_account(): void
    {
        $admin = Admin::first();
        $this->assertNotNull($admin);

        Auth::guard('admin')->setUser($admin);

        $response = $this->get('/account');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('auth.user', null)
            ->where('dbOrders', [])
        );
    }

    /**
     * Scenario 12: High-volume order sync performance and precision.
     */
    public function test_reward_points_high_volume_precision_and_speed(): void
    {
        $user = User::factory()->create();

        // 100 orders of $1.99 -> floor(1.99) = 1 point per order -> 100 + 100 = 200
        $orders = [];
        for ($i = 0; $i < 100; $i++) {
            $orders[] = [
                'order_number' => 'ORD-HV-'.$user->id.'-'.$i,
                'user_id' => $user->id,
                'customer_name' => 'HV User',
                'customer_email' => $user->email,
                'customer_phone' => '1234567890',
                'subtotal' => 1.99,
                'discount' => 0.00,
                'total' => 1.99,
                'refunded_total' => 0.00,
                'points_earned' => 1,
                'payment_method' => 'card',
                'fulfillment_type' => 'Store Pickup',
                'pickup_slot' => 'Today 4–5 pm',
                'pickup_location' => 'Masala Mart',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Order::insert($orders);

        $points = $user->syncAllRewardPoints();

        $this->assertSame(200, $points);
        $this->assertSame(200, $user->fresh()->reward_points);
    }

    /**
     * Scenario 13: Inertia response for /reorder route behaves identically for guest vs auth.
     */
    public function test_inertia_reorder_route_shares_points_and_orders_consistently(): void
    {
        // Guest on /reorder
        $guestResponse = $this->get('/reorder');
        $guestResponse->assertOk();
        $guestResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
            ->where('auth.user', null)
            ->where('dbOrders', [])
        );

        // Authenticated customer on /reorder
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id, 'total' => 50.00, 'status' => 'completed']);

        $authResponse = $this->actingAs($user)->get('/reorder');
        $authResponse->assertOk();
        $authResponse->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.reward_points', 150)
            ->has('dbOrders', 1)
        );
    }
}
