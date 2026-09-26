<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertStatus(200);
    }

    public function test_admin_can_authenticate_using_admin_guard(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->post(route('admin.login'), [
            'email' => 'admin@masalamart.com',
            'password' => 'password',
        ]);

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertEquals($admin->id, Auth::guard('admin')->id());
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admin_cannot_authenticate_with_invalid_password(): void
    {
        $response = $this->post(route('admin.login'), [
            'email' => 'admin@masalamart.com',
            'password' => 'wrong-password',
        ]);

        $this->assertFalse(Auth::guard('admin')->check());
        $response->assertSessionHasErrors('email');
    }

    public function test_unauthenticated_request_to_admin_dashboard_redirects_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_customer_logged_in_on_web_guard_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get(route('admin.dashboard'));

        // Customer on 'web' guard must not access 'admin' guard routes
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

        $this->assertFalse(Auth::guard('admin')->check());
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_has_spatie_roles_and_permissions(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $this->assertTrue($admin->hasRole('Super Admin', 'admin'));
        $this->assertTrue($admin->hasPermissionTo('manage roles', 'admin'));
        $this->assertTrue($admin->hasPermissionTo('manage products', 'admin'));
    }

    public function test_admin_with_permission_can_create_new_spatie_role(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.roles.store'), [
            'name' => 'Inventory Specialist',
            'permissions' => ['manage products'],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('roles', [
            'name' => 'Inventory Specialist',
            'guard_name' => 'admin',
        ]);

        $newRole = Role::findByName('Inventory Specialist', 'admin');
        $this->assertTrue($newRole->hasPermissionTo('manage products', 'admin'));
    }
}
