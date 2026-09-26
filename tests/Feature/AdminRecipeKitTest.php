<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\RecipeKit;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRecipeKitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_guest_cannot_access_admin_recipe_kits(): void
    {
        $response = $this->get(route('admin.recipe-kits.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_recipe_kits_index(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        RecipeKit::create([
            'name' => 'Paneer Butter Masala Kit',
            'slug' => 'paneer-butter-masala-kit',
            'subtitle_tag' => 'CHEF-CRAFTED · DINNER IN 20 MINS',
            'price' => 14.99,
            'original_price' => 18.99,
            'servings' => 'Serves 4',
            'cooking_time' => '20 mins',
            'difficulty' => 'Easy',
            'image' => '/images/products/paneer_curry.jpg',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.recipe-kits.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RecipeKits/Index')
            ->has('recipeKits.data', 1)
        );
    }

    public function test_admin_can_store_recipe_kit_with_linked_products_for_group_buy(): void
    {
        Storage::fake('public');
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $product1 = Product::factory()->create(['name' => 'Malai Paneer', 'price' => 5.99]);
        $product2 = Product::factory()->create(['name' => 'Desi Ghee', 'price' => 9.99]);

        $file = UploadedFile::fake()->image('kit.jpg', 800, 600);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.recipe-kits.store'), [
            'name' => 'Restaurant Paneer Kit',
            'subtitle_tag' => 'READY IN 20 MINS',
            'description' => 'Delicious rich buttery paneer kit with all pre-portioned ingredients.',
            'cooking_time' => '20 mins',
            'servings' => 'Serves 4',
            'difficulty' => 'Easy',
            'price' => 14.99,
            'original_price' => 18.99,
            'is_active' => true,
            'image_files' => [$file],
            'products' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 1,
                    'unit_notes' => '400g cubed malai paneer',
                    'is_optional' => false,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 1,
                    'unit_notes' => 'For gravy tempering',
                    'is_optional' => true,
                ],
            ],
            'recipe_steps' => [
                'Melt ghee and saute spices.',
                'Simmer gravy and fold paneer.',
            ],
        ]);

        $response->assertRedirect(route('admin.recipe-kits.index'));
        $this->assertDatabaseHas('recipe_kits', [
            'name' => 'Restaurant Paneer Kit',
            'slug' => 'restaurant-paneer-kit',
            'price' => 14.99,
        ]);

        $kit = RecipeKit::where('slug', 'restaurant-paneer-kit')->first();
        $this->assertCount(2, $kit->products);
        $this->assertEquals(1, $kit->products->first()->pivot->quantity);
        $this->assertEquals('400g cubed malai paneer', $kit->products->first()->pivot->unit_notes);
    }

    public function test_admin_can_update_recipe_kit_and_sync_products(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $kit = RecipeKit::create([
            'name' => 'Biryani Kit',
            'slug' => 'biryani-kit',
            'price' => 19.99,
            'image' => '/images/products/biryani.jpg',
        ]);

        $product = Product::factory()->create(['name' => 'Basmati Rice', 'price' => 24.99]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.recipe-kits.update', $kit->id), [
            'name' => 'Royal Dum Biryani Kit',
            'slug' => 'royal-dum-biryani-kit',
            'price' => 18.49,
            'original_price' => 22.99,
            'servings' => 'Feast for 4',
            'cooking_time' => '45 mins',
            'difficulty' => 'Medium',
            'is_active' => true,
            'existing_images' => ['/images/products/biryani.jpg'],
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_notes' => '2 bags aged rice',
                    'is_optional' => false,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.recipe-kits.index'));
        $kit->refresh();
        $this->assertEquals('Royal Dum Biryani Kit', $kit->name);
        $this->assertEquals(18.49, (float) $kit->price);
        $this->assertCount(1, $kit->products);
        $this->assertEquals(2, $kit->products->first()->pivot->quantity);
    }

    public function test_customer_can_view_recipe_kit_detail_page(): void
    {
        $kit = RecipeKit::create([
            'name' => 'Paneer Butter Masala Kit',
            'slug' => 'paneer-butter-masala-kit',
            'subtitle_tag' => 'CHEF-CRAFTED · DINNER IN 20 MINS',
            'price' => 14.99,
            'original_price' => 18.99,
            'servings' => 'Serves 4',
            'cooking_time' => '20 mins',
            'difficulty' => 'Easy',
            'image' => '/images/products/paneer_curry.jpg',
            'is_active' => true,
        ]);

        $product = Product::factory()->create(['name' => 'Malai Paneer', 'price' => 5.99]);
        $kit->products()->attach($product->id, ['quantity' => 1, 'unit_notes' => '400g pack']);

        $response = $this->get(route('recipe-kits.show', $kit->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('RecipeKitDetail')
            ->has('kit.products', 1)
        );
    }
}
