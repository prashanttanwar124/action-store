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

    public function test_admin_can_quick_toggle_busy_mode(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.store-info.busy-mode'), [
            'busy_mode_extra_minutes' => 15,
            'busy_mode_reason' => 'Store Rush',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_settings', [
            'busy_mode_extra_minutes' => 15,
            'busy_mode_reason' => 'Store Rush',
        ]);

        $setting = StoreSetting::current();
        $this->assertTrue($setting->is_busy);
        $this->assertStringContainsString('30 mins', $setting->effective_pickup_time);
    }

    public function test_admin_can_update_delivery_and_busy_settings(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.store-info.update'), [
            'name' => 'Masala Mart',
            'tagline' => 'Fresh Groceries',
            'address' => '123 Main St',
            'city' => 'Edison',
            'state' => 'NJ',
            'zip' => '08820',
            'phone' => '+1 (555) 123-4567',
            'email' => 'admin@masalamart.com',
            'opening_hours' => '8 AM - 10 PM',
            'pickup_time' => 'Ready in 15 mins',
            'prep_time_minutes' => 20,
            'busy_mode_extra_minutes' => 10,
            'busy_mode_reason' => 'Evening Rush',
            'is_pickup_active' => true,
            'is_delivery_active' => true,
            'delivery_days' => ['friday', 'saturday'],
            'delivery_fee' => 6.99,
            'free_delivery_threshold' => 60.00,
            'delivery_estimated_time' => 'Friday & Saturday · 4:00 PM – 8:00 PM',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('store_settings', [
            'prep_time_minutes' => 20,
            'busy_mode_extra_minutes' => 10,
            'is_delivery_active' => true,
            'delivery_fee' => 6.99,
            'free_delivery_threshold' => 60.00,
        ]);
    }

    public function test_admin_can_update_pickup_slot_settings(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.store-info.update'), [
            'name' => 'Masala Mart',
            'address' => '123 Main St',
            'city' => 'Edison',
            'state' => 'NJ',
            'zip' => '08820',
            'phone' => '+1 (555) 123-4567',
            'email' => 'admin@masalamart.com',
            'opening_hours' => '8 AM - 10 PM',
            'pickup_slot_window_label' => '30-minute window',
            'pickup_slot_start_time' => '10:00',
            'pickup_slot_end_time' => '18:00',
            'pickup_slot_duration_minutes' => 30,
            'pickup_slots' => [
                ['id' => '10-11', 'startHour' => 10, 'label' => '10:00 AM – 10:30 AM', 'active' => true],
                ['id' => '11-12', 'startHour' => 11, 'label' => '11:00 AM – 11:30 AM', 'active' => false],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('store_settings', [
            'pickup_slot_window_label' => '30-minute window',
            'pickup_slot_start_time' => '10:00',
            'pickup_slot_end_time' => '18:00',
            'pickup_slot_duration_minutes' => 30,
        ]);

        $setting = StoreSetting::current();
        $this->assertCount(1, $setting->available_pickup_slots);
        $this->assertEquals('10:00 AM – 10:30 AM', $setting->available_pickup_slots[0]['label']);
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
