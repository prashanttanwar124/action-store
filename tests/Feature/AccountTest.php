<?php

namespace Tests\Feature;

use App\Models\Order;
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
            ->where('auth.user', null)
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
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'Priya Sharma')
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

    public function test_reorder_page_can_be_viewed(): void
    {
        $response = $this->get('/reorder');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
        );
    }

    public function test_dashboard_redirects_to_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('account'));
    }
}
