<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_guest_cannot_access_admin_products(): void
    {
        $response = $this->get(route('admin.products.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_products_index(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        Product::factory()->create(['name' => 'Chakki Fresh Atta']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.products.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data')
        );
    }

    public function test_admin_can_view_product_create_page(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.products.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Create')
            ->has('defaultCategories')
        );
    }

    public function test_admin_can_store_product_with_multiple_images(): void
    {
        Storage::fake('public');
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $file1 = UploadedFile::fake()->image('fresh_ghee_front.jpg', 600, 600);
        $file2 = UploadedFile::fake()->image('fresh_ghee_back.jpg', 600, 600);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Artisanal Bilona Cow Ghee',
            'slug' => 'artisanal-bilona-cow-ghee',
            'category' => 'dairy',
            'category_title' => 'Dairy, Paneer & Ghee',
            'price' => 14.99,
            'original_price' => 18.99,
            'unit_price' => '($0.94/oz)',
            'stock_badge' => 'Limited Batch',
            'size_main' => '500 ml',
            'size_sub' => 'Glass Jar',
            'freshness_line' => 'Hand-churned from A2 cow curd',
            'description' => 'Traditional bilona ghee made in small batches with golden aroma.',
            'image_files' => [$file1, $file2],
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Artisanal Bilona Cow Ghee',
            'slug' => 'artisanal-bilona-cow-ghee',
            'category' => 'dairy',
            'price' => 14.99,
        ]);

        $product = Product::where('slug', 'artisanal-bilona-cow-ghee')->first();
        $this->assertNotNull($product);
        $this->assertCount(2, $product->images);
        $this->assertEquals($product->images[0], $product->image);

        // Verify stored files in fake storage
        $storedPath1 = str_replace('/storage/', '', $product->images[0]);
        $storedPath2 = str_replace('/storage/', '', $product->images[1]);
        Storage::disk('public')->assertExists($storedPath1);
        Storage::disk('public')->assertExists($storedPath2);
    }

    public function test_admin_can_view_product_edit_page(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $product = Product::factory()->create([
            'name' => 'Kashmiri Saffron',
            'slug' => 'kashmiri-saffron',
            'category' => 'spices',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Edit')
            ->where('product.name', 'Kashmiri Saffron')
        );
    }

    public function test_admin_can_update_product_and_add_images(): void
    {
        Storage::fake('public');
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $product = Product::factory()->create([
            'name' => 'Turmeric Powder',
            'slug' => 'turmeric-powder',
            'price' => 4.99,
            'image' => '/images/products/turmeric.jpg',
            'images' => ['/images/products/turmeric.jpg'],
        ]);

        $newFile = UploadedFile::fake()->image('turmeric_harvest.jpg', 600, 600);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product->id), [
            'name' => 'Organic Salem Turmeric Powder',
            'slug' => 'organic-salem-turmeric-powder',
            'category' => 'spices',
            'price' => 5.49,
            'existing_images' => $product->images,
            'image_files' => [$newFile],
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertEquals('Organic Salem Turmeric Powder', $product->name);
        $this->assertEquals(5.49, $product->price);
        $this->assertCount(2, $product->images);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $product = Product::factory()->create([
            'name' => 'To be deleted product',
            'slug' => 'delete-me',
        ]);

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.products.destroy', $product->id));

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_store_validation_errors(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.products.store'), [
            'name' => '',
            'price' => 'invalid-price',
        ]);

        $response->assertSessionHasErrors(['name', 'category', 'price']);
    }
}
