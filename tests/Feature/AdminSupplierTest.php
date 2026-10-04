<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\Supplier;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
        $this->admin = Admin::where('email', 'admin@masalamart.com')->first();
    }

    public function test_guest_cannot_access_admin_suppliers(): void
    {
        $response = $this->get(route('admin.suppliers.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_suppliers_index_with_metrics(): void
    {
        $supplier = Supplier::create([
            'name' => 'Himalayan Organic Farms',
            'code' => 'SUP-001',
            'contact_person' => 'Rajesh Sharma',
            'email' => 'rajesh@himalayanfarms.in',
            'phone' => '+91 98123 45678',
            'city' => 'Dehradun',
            'state' => 'Uttarakhand',
            'lead_time_days' => 4,
            'payment_terms' => 'Net 30',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Organic Himalayan Honey',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.suppliers.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Suppliers/Index')
            ->has('suppliers.data')
            ->has('metrics.total_suppliers')
            ->has('metrics.active_suppliers')
            ->has('metrics.inactive_suppliers')
            ->has('metrics.total_products_linked')
            ->has('metrics.avg_lead_time_days')
            ->where('suppliers.data.0.name', 'Himalayan Organic Farms')
            ->where('suppliers.data.0.products_count', 1)
        );
    }

    public function test_admin_can_filter_suppliers_by_search_and_status(): void
    {
        Supplier::create([
            'name' => 'Malabar Spice Collective',
            'code' => 'SUP-101',
            'contact_person' => 'Kavita Menon',
            'email' => 'kavita@malabarspice.in',
            'status' => 'active',
        ]);

        Supplier::create([
            'name' => 'Kisan Cold Storage',
            'code' => 'SUP-102',
            'contact_person' => 'Amit Verma',
            'email' => 'amit@kisancold.in',
            'status' => 'inactive',
        ]);

        // Search by query
        $searchResponse = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.suppliers.index', ['search' => 'Malabar']));

        $searchResponse->assertStatus(200);
        $searchResponse->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.code', 'SUP-101')
        );

        // Filter by inactive status
        $statusResponse = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.suppliers.index', ['status' => 'inactive']));

        $statusResponse->assertStatus(200);
        $statusResponse->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.code', 'SUP-102')
        );
    }

    public function test_admin_can_create_supplier_with_auto_generated_code(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.suppliers.store'), [
            'name' => 'Amritsari Ghee Producers',
            'contact_person' => 'Gurpreet Singh',
            'email' => 'gurpreet@amritsarighee.com',
            'phone' => '+91 98765 43210',
            'address' => 'GT Road, Kot Khalsa',
            'city' => 'Amritsar',
            'state' => 'Punjab',
            'country' => 'India',
            'postal_code' => '143001',
            'lead_time_days' => 3,
            'payment_terms' => 'Net 15',
            'status' => 'active',
            'notes' => 'A2 Bilona Cow Ghee certified supplier.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Amritsari Ghee Producers',
            'contact_person' => 'Gurpreet Singh',
            'email' => 'gurpreet@amritsarighee.com',
            'city' => 'Amritsar',
            'lead_time_days' => 3,
            'payment_terms' => 'Net 15',
            'status' => 'active',
        ]);

        $created = Supplier::where('email', 'gurpreet@amritsarighee.com')->first();
        $this->assertNotNull($created);
        $this->assertMatchesRegularExpression('/^SUP-\d{3,}$/', $created->code);
    }

    public function test_admin_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Old Dairy Name',
            'code' => 'SUP-777',
            'contact_person' => 'Old Person',
            'email' => 'old@dairy.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->put(route('admin.suppliers.update', $supplier), [
            'name' => 'New Premium Dairy',
            'code' => 'SUP-777',
            'contact_person' => 'New Person',
            'email' => 'new@dairy.com',
            'phone' => '+91 99999 88888',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'lead_time_days' => 5,
            'payment_terms' => 'Net 30',
            'status' => 'active',
            'notes' => 'Updated notes for dairy supplier',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'New Premium Dairy',
            'email' => 'new@dairy.com',
            'city' => 'Jaipur',
        ]);
    }

    public function test_admin_can_toggle_supplier_status(): void
    {
        $supplier = Supplier::create([
            'name' => 'Organic Seeds Ltd',
            'code' => 'SUP-888',
            'email' => 'seeds@organic.com',
            'status' => 'active',
        ]);

        // Toggle to inactive
        $response = $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.suppliers.toggle-status', $supplier));

        $response->assertRedirect();
        $this->assertEquals('inactive', $supplier->fresh()->status);

        // Toggle back to active
        $response2 = $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.suppliers.toggle-status', $supplier));

        $response2->assertRedirect();
        $this->assertEquals('active', $supplier->fresh()->status);
    }

    public function test_admin_can_delete_supplier_and_products_remain_with_null_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Temporary Vendor',
            'code' => 'SUP-999',
            'email' => 'temp@vendor.com',
            'status' => 'active',
        ]);

        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Test Saffron Box',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.suppliers.destroy', $supplier));

        $response->assertRedirect();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);

        // Check product is intact with supplier_id set to null
        $product->refresh();
        $this->assertNull($product->supplier_id);
        $this->assertEquals('Test Saffron Box', $product->name);
    }

    public function test_admin_can_create_product_with_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Kisan Organics Hub',
            'code' => 'SUP-505',
            'email' => 'hub@kisanorganics.in',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Certified Organic Basmati',
            'category' => 'grocery',
            'category_title' => 'Pantry & Groceries',
            'price' => 14.99,
            'stock' => 80,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'Certified Organic Basmati',
            'supplier_id' => $supplier->id,
        ]);

        $product = Product::where('name', 'Certified Organic Basmati')->first();
        $this->assertEquals($supplier->id, $product->supplier->id);
    }
}
