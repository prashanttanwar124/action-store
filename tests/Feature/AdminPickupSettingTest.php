<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\StoreSetting;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPickupSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_guest_cannot_access_pickup_settings(): void
    {
        $response = $this->get(route('admin.pickup-settings.edit'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_pickup_settings_page(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.pickup-settings.edit'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PickupSettings/Edit')
            ->has('storeInfo')
            ->where('storeInfo.name', 'Masala Mart — Main St.')
        );
    }

    public function test_admin_can_update_pickup_operations_and_slots(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.pickup-settings.update'), [
            'is_pickup_active' => true,
            'prep_time_minutes' => 25,
            'busy_mode_extra_minutes' => 15,
            'busy_mode_reason' => 'Store Crowded',
            'pickup_slot_window_label' => '45-minute window',
            'pickup_slot_start_time' => '10:00',
            'pickup_slot_end_time' => '20:00',
            'pickup_slot_duration_minutes' => 45,
            'curbside_instructions' => 'Wait in Bay 4 with hazard lights on.',
            'pickup_slots' => [
                ['id' => '10-11', 'startHour' => 10, 'label' => '10:00 AM – 10:45 AM', 'active' => true],
                ['id' => '11-12', 'startHour' => 11, 'label' => '11:00 AM – 11:45 AM', 'active' => false],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_settings', [
            'prep_time_minutes' => 25,
            'busy_mode_extra_minutes' => 15,
            'busy_mode_reason' => 'Store Crowded',
            'pickup_slot_window_label' => '45-minute window',
            'pickup_slot_duration_minutes' => 45,
            'curbside_instructions' => 'Wait in Bay 4 with hazard lights on.',
        ]);

        $setting = StoreSetting::current();
        $this->assertTrue($setting->is_busy);
        $this->assertEquals(40, $setting->effective_prep_time_minutes);
        $this->assertCount(1, $setting->available_pickup_slots);
        $this->assertEquals('10:00 AM – 10:45 AM', $setting->available_pickup_slots[0]['label']);
    }
}
