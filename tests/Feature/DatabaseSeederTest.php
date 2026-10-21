<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that DatabaseSeeder executes cleanly with exit code 0 on a fresh database.
     */
    public function test_database_seeder_executes_without_error(): void
    {
        $exitCode = Artisan::call('db:seed');

        $this->assertSame(0, $exitCode, 'Artisan db:seed should exit with code 0');
    }

    /**
     * Test that DatabaseSeeder seeds all expected users with correct attributes.
     */
    public function test_database_seeder_populates_expected_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 6);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'priya@example.com',
            'name' => 'Priya Sharma',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'rahul@example.com',
            'name' => 'Rahul Khanna',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'amit@example.com',
            'name' => 'Amit Patel',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'vikram@example.com',
            'name' => 'Vikram Malhotra',
        ]);

        $unverified = User::where('email', 'ananya@example.com')->first();
        $this->assertNotNull($unverified);
        $this->assertSame('Ananya Roy', $unverified->name);
        $this->assertNull($unverified->email_verified_at);
    }

    /**
     * Test that DatabaseSeeder populates products across distinct categories with stock and pricing.
     */
    public function test_database_seeder_populates_products_and_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(20, Product::count(), 'Expected 20 products seeded');

        $categories = Product::distinct()->pluck('category')->sort()->values()->toArray();
        $this->assertContains('staples', $categories);
        $this->assertContains('dairy', $categories);
        $this->assertContains('spices', $categories);
        $this->assertContains('produce', $categories);
        $this->assertContains('snacks', $categories);
        $this->assertContains('sweets', $categories);

        $this->assertDatabaseHas('products', [
            'slug' => 'atta',
            'name' => 'Chakki Atta',
            'category' => 'staples',
        ]);
        $this->assertDatabaseHas('products', [
            'slug' => 'ghee',
            'name' => 'Desi Ghee',
            'category' => 'dairy',
        ]);

        $this->assertGreaterThanOrEqual(6, Category::count());
    }

    /**
     * Test that DatabaseSeeder seeds suppliers with valid codes and product associations.
     */
    public function test_database_seeder_populates_suppliers_with_codes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Supplier::count(), 'Expected 5 suppliers seeded');

        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-001',
            'name' => 'Amrit Farm Dairy',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-002',
            'name' => 'Punjab Heritage Mills',
            'status' => 'active',
        ]);

        $amrit = Supplier::where('code', 'SUP-001')->first();
        $this->assertNotNull($amrit);
        $this->assertNotEmpty($amrit->products);
    }

    /**
     * Test that DatabaseSeeder populates recipe kits with linked ingredients.
     */
    public function test_database_seeder_populates_recipe_kits_and_ingredients(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, RecipeKit::count(), 'Expected 4 recipe kits seeded');

        $paneerKit = RecipeKit::where('slug', 'paneer-curry')->first();
        $this->assertNotNull($paneerKit);
        $this->assertSame('Paneer Butter Masala Kit', $paneerKit->name);
        $this->assertGreaterThan(0, $paneerKit->products()->count(), 'Recipe kit must have attached products');
    }

    /**
     * Test that DatabaseSeeder populates sliders and store settings.
     */
    public function test_database_seeder_populates_sliders_and_store_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Slider::count(), 'Expected 3 homepage promo sliders seeded');
        $this->assertSame(3, Slider::where('is_active', true)->count());

        $storeSetting = StoreSetting::current();
        $this->assertNotNull($storeSetting);
        $this->assertSame('Masala Mart — Main St.', $storeSetting->name);
        $this->assertTrue($storeSetting->is_pickup_active);
    }

    /**
     * Test that DatabaseSeeder sets up Spatie permissions and roles, including manage settings.
     */
    public function test_database_seeder_populates_roles_permissions_and_admins(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'manage settings', 'guard_name' => 'admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage products', 'guard_name' => 'admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage orders', 'guard_name' => 'admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'manage users', 'guard_name' => 'admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'view reports', 'guard_name' => 'admin']);

        $superAdminRole = Role::findByName('Super Admin', 'admin');
        $this->assertNotNull($superAdminRole);
        $this->assertTrue($superAdminRole->hasPermissionTo('manage settings'));
        $this->assertTrue($superAdminRole->hasPermissionTo('manage products'));

        $storeManagerRole = Role::findByName('Store Manager', 'admin');
        $this->assertNotNull($storeManagerRole);
        $this->assertTrue($storeManagerRole->hasPermissionTo('manage settings'));
        $this->assertTrue($storeManagerRole->hasPermissionTo('manage products'));

        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('Super Admin', 'admin'));
        $this->assertTrue($admin->can('manage settings'));

        $manager = Admin::where('email', 'manager@masalamart.com')->first();
        $this->assertNotNull($manager);
        $this->assertTrue($manager->hasRole('Store Manager', 'admin'));
        $this->assertTrue($manager->can('manage settings'));
    }

    /**
     * Test that DatabaseSeeder populates sample orders.
     */
    public function test_database_seeder_populates_orders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Order::count(), 'Expected 5 demo orders seeded');
        $this->assertDatabaseHas('orders', ['order_number' => '#MM-15508', 'customer_name' => 'Priya Sharma']);
        $this->assertDatabaseHas('orders', ['order_number' => '#MM-15505']);
        $this->assertGreaterThan(0, Order::first()->items()->count());
    }

    /**
     * Test that DatabaseSeeder can be run multiple times idempotently without throwing errors.
     */
    public function test_database_seeder_is_idempotent(): void
    {
        $firstRun = Artisan::call('db:seed');
        $this->assertSame(0, $firstRun);

        $secondRun = Artisan::call('db:seed');
        $this->assertSame(0, $secondRun, 'Second run of db:seed should succeed without errors');

        $this->assertSame(20, Product::count());
        $this->assertSame(5, Supplier::count());
        $this->assertSame(4, RecipeKit::count());
        $this->assertSame(3, Slider::count());
        $this->assertSame(2, Admin::count());
    }
}
