<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->first();
    }

    public function test_guest_cannot_access_admin_customers(): void
    {
        $response = $this->get(route('admin.customers.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_customers_index_with_metrics(): void
    {
        $user = User::factory()->create([
            'name' => 'Meera Patel',
            'email' => 'meera@example.com',
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'total' => 65.00,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.customers.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data')
            ->has('metrics.total_customers')
            ->has('metrics.active_shoppers')
            ->has('metrics.total_orders')
            ->has('metrics.total_revenue')
            ->has('metrics.average_spend')
            ->where('customers.data.0.email', 'meera@example.com')
            ->where('customers.data.0.orders_count', 1)
            ->where('customers.data.0.total_spent', 65)
        );
    }

    public function test_admin_can_filter_and_search_customers(): void
    {
        User::factory()->create([
            'name' => 'Kavita Krishnan',
            'email' => 'kavita@example.com',
        ]);

        User::factory()->create([
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@example.com',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.customers.index', [
            'search' => 'Kavita',
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Kavita Krishnan')
        );
    }

    public function test_admin_can_view_customer_show_api(): void
    {
        $user = User::factory()->create([
            'name' => 'Suresh Raina',
            'email' => 'suresh@example.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'total' => 45.00,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.customers.show', $user->id));

        $response->assertStatus(200);
        $response->assertJsonPath('customer.name', 'Suresh Raina');
        $response->assertJsonPath('customer.orders_count', 1);
        $response->assertJsonPath('orders.0.order_number', $order->order_number);
    }

    public function test_admin_can_create_customer(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.customers.store'), [
            'name' => 'Deepak Chopra',
            'email' => 'deepak@example.com',
            'password' => 'password123',
            'email_verified' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Deepak Chopra',
            'email' => 'deepak@example.com',
        ]);

        $user = User::where('email', 'deepak@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_admin_can_update_customer(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->patch(route('admin.customers.update', $user->id), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'email_verified' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_admin_can_delete_customer_and_orders_are_preserved(): void
    {
        $user = User::factory()->create([
            'name' => 'Customer To Delete',
            'email' => 'delete_me@example.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->delete(route('admin.customers.destroy', $user->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        // Historical order still exists with user_id null
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => null,
            'customer_name' => 'Customer To Delete',
        ]);
    }
}
