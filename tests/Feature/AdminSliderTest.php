<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Slider;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSliderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    public function test_guest_cannot_access_admin_sliders(): void
    {
        $response = $this->get(route('admin.sliders.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_sliders_index(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        Slider::create([
            'title' => "Diwali sweets,\nboxed &\nready.",
            'tag' => 'FESTIVAL PRE-ORDER',
            'cta_text' => 'Order now',
            'link_url' => '/products/sweets',
            'image' => '/images/products/sweets.jpg',
            'photo_label' => 'mithai box',
            'bg_color' => '#1a1a1a',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.sliders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Sliders/Index')
            ->has('sliders', 1)
        );
    }

    public function test_admin_can_view_slider_create_page(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.sliders.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Sliders/Create')
            ->has('products')
            ->has('recipeKits')
        );
    }

    public function test_admin_can_store_slider_with_image_upload(): void
    {
        Storage::fake('public');
        $admin = Admin::where('email', 'admin@masalamart.com')->first();

        $file = UploadedFile::fake()->image('banner.jpg', 1200, 600);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.sliders.store'), [
            'title' => "Fresh Harvest,\nFarm to\nTable.",
            'tag' => 'DAILY HARVEST',
            'cta_text' => 'Explore Veggies',
            'link_url' => '/products/okra',
            'photo_label' => 'farm fresh',
            'bg_color' => '#064e3b',
            'sort_order' => 1,
            'is_active' => true,
            'image_file' => $file,
        ]);

        $response->assertRedirect(route('admin.sliders.index'));
        $this->assertDatabaseHas('sliders', [
            'tag' => 'DAILY HARVEST',
            'bg_color' => '#064e3b',
        ]);

        $slider = Slider::where('tag', 'DAILY HARVEST')->first();
        $this->assertNotNull($slider->image);
        $this->assertStringStartsWith('/storage/sliders/', $slider->image);
    }

    public function test_admin_can_update_slider(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $slider = Slider::create([
            'title' => 'Original Headline',
            'tag' => 'ORIGINAL',
            'cta_text' => 'Click Here',
            'link_url' => '/old-url',
            'image' => '/images/products/sweets.jpg',
            'photo_label' => 'old label',
            'bg_color' => '#1a1a1a',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.sliders.update', $slider->id), [
            'title' => 'Updated Headline',
            'tag' => 'UPDATED TAG',
            'cta_text' => 'Order Today',
            'link_url' => '/new-url',
            'photo_label' => 'new label',
            'bg_color' => '#221c17',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.sliders.index'));
        $slider->refresh();
        $this->assertEquals('Updated Headline', $slider->title);
        $this->assertEquals('UPDATED TAG', $slider->tag);
        $this->assertEquals('#221c17', $slider->bg_color);
        $this->assertFalse((bool) $slider->is_active);
    }

    public function test_admin_can_delete_slider(): void
    {
        $admin = Admin::where('email', 'admin@masalamart.com')->first();
        $slider = Slider::create([
            'title' => 'To Be Deleted',
            'cta_text' => 'Delete Me',
            'link_url' => '/delete',
            'image' => '/images/products/sweets.jpg',
            'bg_color' => '#1a1a1a',
        ]);

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.sliders.destroy', $slider->id));

        $response->assertRedirect(route('admin.sliders.index'));
        $this->assertDatabaseMissing('sliders', ['id' => $slider->id]);
    }
}
