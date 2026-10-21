<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityMilestone1AdversarialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Matrix 1: Authorization Gating Adversarial Stress Tests
    |--------------------------------------------------------------------------
    | Endpoints tested:
    | 1. GET   /admin/orders                   (admin.orders.index)
    | 2. PATCH /admin/orders/{id}/status       (admin.orders.status.update)
    | 3. POST  /admin/orders/{id}/items/{item}/refund (admin.orders.items.refund)
    | 4. PATCH /admin/orders/{id}/items/packed (admin.orders.items.packed)
    |
    | Actors tested:
    | - Unauthenticated Guest (Web standard redirect 302 & JSON 401)
    | - Authenticated Customer User (Web standard redirect 302 & JSON 401)
    | - Authenticated Admin with ONLY 'manage products' permission (403 Forbidden)
    | - Authenticated Admin with NO permissions (403 Forbidden)
    | - Authenticated Admin with 'manage orders' permission (Positive Control)
    */

    public function test_unauthenticated_guest_accessing_admin_orders_index_is_rejected(): void
    {
        // Standard Web Request: Redirects to admin.login
        $webResponse = $this->get(route('admin.orders.index'));
        $webResponse->assertRedirect(route('admin.login'));

        // JSON / API Request: 401 Unauthorized
        $jsonResponse = $this->getJson(route('admin.orders.index'));
        $jsonResponse->assertStatus(401);
    }

    public function test_unauthenticated_guest_updating_admin_order_status_is_rejected(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed']);

        // Web Request
        $webResponse = $this->patch(route('admin.orders.status.update', $order), [
            'status' => 'cancelled',
        ]);
        $webResponse->assertRedirect(route('admin.login'));
        $this->assertSame('confirmed', $order->fresh()->status);

        // JSON Request
        $jsonResponse = $this->patchJson(route('admin.orders.status.update', $order), [
            'status' => 'cancelled',
        ]);
        $jsonResponse->assertStatus(401);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_unauthenticated_guest_refunding_order_item_is_rejected(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'total' => 50]);
        $product = Product::factory()->create(['price' => 25]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25,
            'quantity' => 2,
            'total_price' => 50,
        ]);

        // Web Request
        $webResponse = $this->post(route('admin.orders.items.refund', [$order, $item]), [
            'quantity' => 1,
            'reason' => 'Unauthorized refund attempt',
        ]);
        $webResponse->assertRedirect(route('admin.login'));
        $this->assertSame(0.0, (float) $order->fresh()->refunded_total);

        // JSON Request
        $jsonResponse = $this->postJson(route('admin.orders.items.refund', [$order, $item]), [
            'quantity' => 1,
            'reason' => 'Unauthorized refund attempt',
        ]);
        $jsonResponse->assertStatus(401);
        $this->assertSame(0.0, (float) $order->fresh()->refunded_total);
    }

    public function test_authenticated_customer_user_accessing_admin_orders_index_is_rejected(): void
    {
        $customer = User::factory()->create();

        // Standard Web Request with Customer Auth
        $webResponse = $this->actingAs($customer, 'web')
            ->get(route('admin.orders.index'));
        $webResponse->assertRedirect(route('admin.login'));

        // JSON Request with Customer Auth
        $jsonResponse = $this->actingAs($customer, 'web')
            ->getJson(route('admin.orders.index'));
        $jsonResponse->assertStatus(401);
    }

    public function test_authenticated_customer_user_updating_admin_order_status_is_rejected(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => 'confirmed']);

        // Web Request
        $webResponse = $this->actingAs($customer, 'web')
            ->patch(route('admin.orders.status.update', $order), [
                'status' => 'completed',
            ]);
        $webResponse->assertRedirect(route('admin.login'));
        $this->assertSame('confirmed', $order->fresh()->status);

        // JSON Request
        $jsonResponse = $this->actingAs($customer, 'web')
            ->patchJson(route('admin.orders.status.update', $order), [
                'status' => 'completed',
            ]);
        $jsonResponse->assertStatus(401);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_authenticated_customer_user_refunding_order_item_is_rejected(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['status' => 'packing', 'total' => 60]);
        $product = Product::factory()->create(['price' => 30]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 30,
            'quantity' => 2,
            'total_price' => 60,
        ]);

        // Web Request
        $webResponse = $this->actingAs($customer, 'web')
            ->post(route('admin.orders.items.refund', [$order, $item]), [
                'quantity' => 1,
                'reason' => 'Malicious refund by customer',
            ]);
        $webResponse->assertRedirect(route('admin.login'));
        $this->assertSame(0.0, (float) $order->fresh()->refunded_total);

        // JSON Request
        $jsonResponse = $this->actingAs($customer, 'web')
            ->postJson(route('admin.orders.items.refund', [$order, $item]), [
                'quantity' => 1,
                'reason' => 'Malicious refund by customer',
            ]);
        $jsonResponse->assertStatus(401);
        $this->assertSame(0.0, (float) $order->fresh()->refunded_total);
    }

    public function test_admin_with_only_manage_products_permission_is_forbidden_on_all_order_endpoints(): void
    {
        $productAdmin = Admin::create([
            'name' => 'Product Only Admin',
            'email' => 'products-only@masalamart.com',
            'password' => Hash::make('password123'),
        ]);
        $productRole = Role::create(['name' => 'Product Specialist', 'guard_name' => 'admin']);
        $productRole->givePermissionTo('manage products');
        $productAdmin->assignRole($productRole);

        $order = Order::factory()->create(['status' => 'confirmed', 'total' => 100]);
        $product = Product::factory()->create(['price' => 50]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 50,
            'quantity' => 2,
            'total_price' => 100,
        ]);

        // 1. GET /admin/orders -> 403
        $indexResponse = $this->actingAs($productAdmin, 'admin')
            ->get(route('admin.orders.index'));
        $indexResponse->assertStatus(403);

        $indexJsonResponse = $this->actingAs($productAdmin, 'admin')
            ->getJson(route('admin.orders.index'));
        $indexJsonResponse->assertStatus(403);

        // 2. PATCH /admin/orders/{order}/status -> 403
        $statusResponse = $this->actingAs($productAdmin, 'admin')
            ->patch(route('admin.orders.status.update', $order), [
                'status' => 'completed',
            ]);
        $statusResponse->assertStatus(403);
        $this->assertSame('confirmed', $order->fresh()->status);

        // 3. POST /admin/orders/{order}/items/{item}/refund -> 403
        $refundResponse = $this->actingAs($productAdmin, 'admin')
            ->post(route('admin.orders.items.refund', [$order, $item]), [
                'quantity' => 1,
                'reason' => 'Attempted by product manager',
            ]);
        $refundResponse->assertStatus(403);
        $this->assertSame(0.0, (float) $order->fresh()->refunded_total);

        // 4. PATCH /admin/orders/{order}/items/packed -> 403
        $packedResponse = $this->actingAs($productAdmin, 'admin')
            ->patch(route('admin.orders.items.packed', $order), [
                'is_packed' => true,
            ]);
        $packedResponse->assertStatus(403);
    }

    public function test_admin_with_no_permissions_is_forbidden_on_order_endpoints(): void
    {
        $bareAdmin = Admin::create([
            'name' => 'Bare Admin',
            'email' => 'bare@masalamart.com',
            'password' => Hash::make('password123'),
        ]);

        $order = Order::factory()->create(['status' => 'confirmed']);

        $this->actingAs($bareAdmin, 'admin')
            ->get(route('admin.orders.index'))
            ->assertStatus(403);

        $this->actingAs($bareAdmin, 'admin')
            ->patch(route('admin.orders.status.update', $order), ['status' => 'cancelled'])
            ->assertStatus(403);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_admin_with_manage_orders_permission_can_access_and_mutate_orders(): void
    {
        $orderStaff = Admin::create([
            'name' => 'Order Staff Member',
            'email' => 'orderstaff@masalamart.com',
            'password' => Hash::make('password123'),
        ]);
        $orderRole = Role::where('name', 'Order Handler')->where('guard_name', 'admin')->first();
        $orderStaff->assignRole($orderRole);

        $order = Order::factory()->create(['status' => 'confirmed']);

        // Index accessible
        $this->actingAs($orderStaff, 'admin')
            ->get(route('admin.orders.index'))
            ->assertStatus(200);

        // Status updatable
        $this->actingAs($orderStaff, 'admin')
            ->patch(route('admin.orders.status.update', $order), ['status' => 'packing'])
            ->assertRedirect();
        $this->assertSame('packing', $order->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Matrix 2: Mass Assignment Adversarial Stress Tests
    |--------------------------------------------------------------------------
    | Testing reward_points protection on User::create, User::update, User::fill
    | across various payloads, key casings, data types, and HTTP endpoints.
    */

    public function test_mass_assignment_user_create_with_integer_points(): void
    {
        $user = User::create([
            'name' => 'Adversary Int',
            'email' => 'adversary_int@test.com',
            'password' => Hash::make('secret123'),
            'reward_points' => 999999,
        ]);

        $this->assertNotEquals(999999, $user->reward_points);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_create_with_string_points(): void
    {
        $user = User::create([
            'name' => 'Adversary String',
            'email' => 'adversary_str@test.com',
            'password' => Hash::make('secret123'),
            'reward_points' => '50000',
        ]);

        $this->assertNotEquals('50000', $user->reward_points);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_create_with_negative_points(): void
    {
        $user = User::create([
            'name' => 'Adversary Negative',
            'email' => 'adversary_neg@test.com',
            'password' => Hash::make('secret123'),
            'reward_points' => -500,
        ]);

        $this->assertNotEquals(-500, $user->reward_points);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_create_with_zero_points(): void
    {
        $user = User::create([
            'name' => 'Adversary Zero',
            'email' => 'adversary_zero@test.com',
            'password' => Hash::make('secret123'),
            'reward_points' => 0,
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_create_with_array_payload(): void
    {
        $user = User::create([
            'name' => 'Adversary Array',
            'email' => 'adversary_arr@test.com',
            'password' => Hash::make('secret123'),
            'reward_points' => ['exploit' => 12345],
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_create_with_alternate_key_casings(): void
    {
        $user = User::create([
            'name' => 'Adversary Casings',
            'email' => 'adversary_casings@test.com',
            'password' => Hash::make('secret123'),
            'rewardPoints' => 88888,
            'reward_point' => 77777,
            'points' => 66666,
            'REWARD_POINTS' => 55555,
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_update_with_various_keys(): void
    {
        $user = User::create([
            'name' => 'Legit User Before Update',
            'email' => 'legit_user@test.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Attempt integer update
        $user->update(['reward_points' => 99999]);
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Attempt string update
        $user->update(['reward_points' => '50000']);
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Attempt negative update
        $user->update(['reward_points' => -200]);
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Attempt alternate key casing
        $user->update(['rewardPoints' => 77777, 'points' => 44444]);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_mass_assignment_user_fill_and_save(): void
    {
        $user = User::create([
            'name' => 'Fill Test User',
            'email' => 'fill_user@test.com',
            'password' => Hash::make('secret123'),
        ]);

        $user->fill([
            'name' => 'Renamed Fill User',
            'reward_points' => 88888,
        ]);

        $this->assertFalse($user->isDirty('reward_points'));
        $this->assertTrue($user->isDirty('name'));

        $user->save();

        $this->assertSame('Renamed Fill User', $user->fresh()->name);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_registration_endpoint_ignores_reward_points_injection(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'HTTP Exploit User',
            'email' => 'httpexploit@test.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'reward_points' => 999999,
        ]);

        $response->assertRedirect();

        $createdUser = User::where('email', 'httpexploit@test.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame(100, (int) $createdUser->reward_points);
        $this->assertSame(100, (int) $createdUser->fresh()->reward_points);
    }

    public function test_profile_update_endpoint_ignores_reward_points_injection(): void
    {
        $user = User::factory()->create();
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Updated Profile Name',
            'email' => $user->email,
            'reward_points' => 999999,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Updated Profile Name', $user->fresh()->name);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }
}
