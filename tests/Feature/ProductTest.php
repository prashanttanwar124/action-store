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
            ->where('totalProductCount', 14)
            ->where('products.0.slug', 'atta')
        );
    }

    public function test_search_route_renders_search_catalogue_with_products(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(RecipeKitSeeder::class);

        $response = $this->get('/search');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('products.data', 10)
            ->where('products.total', 14)
            ->where('products.current_page', 1)
            ->has('recipeKits')
            ->has('categories')
        );
    }

    public function test_search_route_supports_query_filtering_and_pagination_url(): void
    {
        $this->seed(ProductSeeder::class);

        $response = $this->get('/search?q=paneer');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'paneer')
            ->where('filters.q', 'paneer')
        );

        $page2Response = $this->get('/search?page=2');
        $page2Response->assertStatus(200);
        $page2Response->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('products.current_page', 2)
            ->has('products.data', 4)
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

    public function test_category_route_filters_products_by_category(): void
    {
        $this->seed(ProductSeeder::class);

        $response = $this->get('/categories/dairy');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('filters.category', 'dairy')
            ->has('products.data')
        );
    }

    public function test_recipe_kits_index_route_loads_recipe_kits_catalogue(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(RecipeKitSeeder::class);

        $response = $this->get('/recipe-kits');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('filters.tab', 'kits')
            ->has('recipeKits.data')
        );
    }
}
