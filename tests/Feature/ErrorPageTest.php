<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_non_existent_page_renders_blade_404_view_on_standard_request(): void
    {
        $response = $this->get('/non-existent-page-url-xyz');

        $response->assertStatus(404);
        $response->assertSee('AISLE NOT FOUND');
        $response->assertSee('Masala Mart');
    }

    public function test_non_existent_page_renders_inertia_error_component_on_inertia_request(): void
    {
        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('/non-existent-page-url-xyz');

        $response->assertStatus(404);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 404,
            ],
        ]);
    }

    public function test_cart_page_renders_with_store_pickup(): void
    {
        $response = $this->get(route('cart'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Cart'));
    }

    public function test_checkout_page_renders_with_store_pickup(): void
    {
        $response = $this->get(route('checkout'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Checkout'));
    }
}
