<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->first();
    }

    public function test_guest_cannot_access_admin_categories(): void
    {
        $response = $this->get(route('admin.categories.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_categories_index_with_metrics(): void
    {
        $cat = Category::create([
            'name' => 'Organic Lentils',
            'slug' => 'organic-lentils',
            'hindi_title' => 'दाल',
            'description' => 'Rich in protein lentils',
            'icon' => 'Wheat',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category' => 'organic-lentils',
            'category_title' => 'Organic Lentils',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data')
            ->has('metrics.total_categories')
            ->has('metrics.active_categories')
            ->has('metrics.total_products')
            ->where('categories.data.0.slug', 'organic-lentils')
            ->where('categories.data.0.products_count', 1)
        );
    }

    public function test_admin_can_filter_categories_by_search_and_status(): void
    {
        Category::create([
            'name' => 'Ayurvedic Teas',
            'slug' => 'ayurvedic-teas',
            'hindi_title' => 'चाय',
            'icon' => 'Coffee',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Seasonal Mangoes',
            'slug' => 'seasonal-mangoes',
            'hindi_title' => 'आम',
            'icon' => 'Apple',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        // Search test
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index', ['search' => 'Ayurvedic']));
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.slug', 'ayurvedic-teas')
        );

        // Status test (inactive only)
        $statusResponse = $this->actingAs($this->admin, 'admin')->get(route('admin.categories.index', ['status' => 'inactive']));
        $statusResponse->assertStatus(200);
        $statusResponse->assertInertia(fn (Assert $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.slug', 'seasonal-mangoes')
        );
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.categories.store'), [
            'name' => 'Artisanal Pickles & Achars',
            'slug' => 'artisanal-pickles',
            'hindi_title' => 'अचार',
            'description' => 'Grandmother recipe sun-dried mango and lime achars.',
            'icon' => 'Flame',
            'image_url' => '/images/products/spices.jpg',
            'sort_order' => 9,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'name' => 'Artisanal Pickles & Achars',
            'slug' => 'artisanal-pickles',
            'hindi_title' => 'अचार',
            'icon' => 'Flame',
            'sort_order' => 9,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_category_and_cascades_to_products(): void
    {
        $category = Category::create([
            'name' => 'Dairy & Milk',
            'slug' => 'dairy-milk',
            'hindi_title' => 'दूध',
            'icon' => 'Milk',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'dairy-milk',
            'category_title' => 'Dairy & Milk',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->patch(route('admin.categories.update', $category), [
            'name' => 'Artisanal Dairy & Pure Ghee',
            'slug' => 'dairy-ghee',
            'hindi_title' => 'डेयरी व घी',
            'icon' => 'Milk',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Artisanal Dairy & Pure Ghee',
            'slug' => 'dairy-ghee',
        ]);

        // Verify product category slug and title cascaded
        $product->refresh();
        $this->assertEquals('dairy-ghee', $product->category);
        $this->assertEquals('Artisanal Dairy & Pure Ghee', $product->category_title);
    }

    public function test_admin_can_delete_category_and_reassigns_products_to_grocery(): void
    {
        $category = Category::create([
            'name' => 'Temporary Promo Aisle',
            'slug' => 'temporary-promo',
            'icon' => 'Tag',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category' => 'temporary-promo',
            'category_title' => 'Temporary Promo Aisle',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->delete(route('admin.categories.destroy', $category));

        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);

        // Products should safely fall back to 'grocery'
        $product->refresh();
        $this->assertEquals('grocery', $product->category);
        $this->assertEquals('Pantry & Groceries', $product->category_title);
    }

    public function test_admin_can_toggle_category_active_status(): void
    {
        $category = Category::create([
            'name' => 'Seasonal Sweets',
            'slug' => 'seasonal-sweets',
            'icon' => 'Sparkles',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.categories.toggle', $category));

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'is_active' => false]);

        $category->refresh();
        $this->assertFalse($category->is_active);
    }
}
