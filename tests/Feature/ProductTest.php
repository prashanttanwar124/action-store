<?php

namespace Tests\Feature;

use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_products_from_database(): void
    {
        $this->seed(ProductSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('products', 15)
            ->where('products.0.slug', 'atta')
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

    public function test_recipe_kit_detail_page_loads_kit_ingredients(): void
    {
        $this->seed(ProductSeeder::class);

        $response = $this->get('/products/paneer-curry');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ProductDetail')
            ->where('slug', 'paneer-curry')
            ->where('product.is_recipe_kit', true)
            ->has('product.recipe_ingredients', 4)
        );
    }
}
