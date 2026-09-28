<?php

namespace Tests\Feature;

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
