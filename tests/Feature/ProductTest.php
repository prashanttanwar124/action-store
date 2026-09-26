<?php

namespace Tests\Feature;

use Database\Seeders\ProductSeeder;
use Database\Seeders\RecipeKitSeeder;
use Database\Seeders\SliderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_products_and_recipe_kits_from_database(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(RecipeKitSeeder::class);
        $this->seed(SliderSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('products', 10)
            ->has('sliders')
            ->has('recipeKits')
            ->where('products.0.slug', 'atta')
        );
    }

    public function test_search_route_loads_home_with_focus_search(): void
    {
        $this->seed(ProductSeeder::class);

        $response = $this->get('/search');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('focusSearch', true)
        );
    }

    public function test_product_detail_page_loads_correct_product(): void
    {
        $this->seed(ProductSeeder::class);

        $response = $this->get('/products/paneer');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProductDetail')
            ->where('slug', 'paneer')
            ->where('product.name', 'Malai Paneer')
            ->where('product.price', 5.99)
        );
    }

    public function test_product_route_redirects_to_recipe_kit_when_slug_matches_kit(): void
    {
        $this->seed(RecipeKitSeeder::class);

        $response = $this->get('/products/paneer-curry');

        $response->assertRedirect(route('recipe-kits.show', 'paneer-curry'));
    }

    public function test_product_detail_returns_404_when_neither_product_nor_kit_exists(): void
    {
        $response = $this->get('/products/non-existent-item');

        $response->assertNotFound();
    }

    public function test_recipe_kit_detail_page_loads_kit_ingredients(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(RecipeKitSeeder::class);

        $response = $this->get('/recipe-kits/paneer-curry');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('RecipeKitDetail')
            ->where('kit.slug', 'paneer-curry')
            ->has('kit.products')
        );
    }

    public function test_cart_and_checkout_pages_render(): void
    {
        $this->get('/cart')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Cart'));
        $this->get('/checkout')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Checkout'));
    }

    public function test_account_and_reorder_pages_render(): void
    {
        $this->get('/account')->assertStatus(200)->assertInertia(fn (Assert $page) => $page->component('Account'));
        $this->get('/reorder')->assertStatus(200)->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('activeSection', 'reorder')
        );
    }
}
