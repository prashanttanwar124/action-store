<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\StoreSetting;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminStoreSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_guest_cannot_access_store_settings(): void
    {
        $response = $this->get(route('admin.store-info.edit'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_store_settings_page(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.store-info.edit'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StoreInfo/Edit')
            ->has('storeInfo')
            ->where('storeInfo.name', 'Masala Mart — Main St.')
        );
    }

    public function test_admin_can_update_store_settings(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.store-info.update'), [
            'name' => 'Masala Mart — Central Flagship',
            'tagline' => 'Fresh Indian Groceries & Daily Essentials',
            'hindi_tagline' => 'किराना स्टोर',
            'address' => '789 Oak Tree Road',
            'city' => 'Edison',
            'state' => 'NJ',
            'zip' => '08820',
            'phone' => '+1 (732) 555-9876',
            'email' => 'contact@masalamart.com',
            'opening_hours' => 'Mon–Sun: 8:00 AM – 10:00 PM',
            'pickup_time' => 'Ready in 30 mins',
            'curbside_instructions' => 'Park in Bay 1 or 2, flash hazard lights, and our team will bring your bagged groceries.',
            'announcement' => 'Grand Opening Celebration: Free spice kit with every $50 order!',
            'maps_url' => 'https://maps.google.com/?q=789+Oak+Tree+Road,+Edison,+NJ',
            'is_pickup_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_settings', [
            'name' => 'Masala Mart — Central Flagship',
            'address' => '789 Oak Tree Road',
            'pickup_time' => 'Ready in 30 mins',
            'phone' => '+1 (732) 555-9876',
        ]);
    }

    public function test_store_info_is_shared_globally_on_storefront_pages(): void
    {
        StoreSetting::current()->update([
            'name' => 'Masala Mart Express',
            'pickup_time' => 'Ready in 20 mins',
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('storeInfo')
            ->where('storeInfo.name', 'Masala Mart Express')
            ->where('storeInfo.pickup_time', 'Ready in 20 mins')
        );
    }
}
