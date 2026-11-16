<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\RewardPoint;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\PosRequest;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected Admin $manager;

    protected Admin $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));
        $this->seed(AdminSeeder::class);

        $this->superAdmin = Admin::where('email', 'admin@masalamart.com')->firstOrFail();
        $this->manager = Admin::where('email', 'manager@masalamart.com')->firstOrFail();

        $this->cashier = Admin::create([
            'name' => 'Counter Cashier',
            'email' => 'cashier@masalamart.com',
            'password' => Hash::make('password'),
            'pos_pin' => '3333',
        ]);
        $this->cashier->assignRole('Cashier');
    }

    public function test_unauthenticated_visitor_on_pos_redirects_to_pos_login(): void
    {
        $this->get('/pos')->assertRedirect('/pos/login');

        $loginResponse = $this->get('/pos/login');
        $loginResponse->assertOk();
        $loginResponse->assertViewIs('pos');
        $loginResponse->assertInertia(fn (Assert $page) => $page->component('Pos/Login'));
    }

    public function test_pos_ip_allowlist_blocks_unauthorized_ips_and_allows_configured_store_ip(): void
    {
        config(['pos.allowed_ips' => ['203.0.113.10']]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->get('/pos/login')
            ->assertForbidden();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/pos/login')
            ->assertOk();
    }

    public function test_staff_without_operate_pos_permission_cannot_login_or_access_pos_register(): void
    {
        $orderHandler = Admin::create([
            'name' => 'Packer Only',
            'email' => 'packer@masalamart.com',
            'password' => Hash::make('password'),
        ]);
        $orderHandler->assignRole('Order Handler');

        $this->post('/pos/login', [
            'email' => 'packer@masalamart.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');

        $this->actingAs($orderHandler, 'admin')
            ->get('/pos')
            ->assertForbidden();

        $this->post('/pos/login', [
            'email' => 'cashier@masalamart.com',
            'password' => 'password',
        ])->assertRedirect('/pos');

        $this->assertAuthenticatedAs($this->cashier, 'admin');
    }

    public function test_cashier_can_switch_active_staff_via_four_digit_pin_and_rejects_wrong_pin(): void
    {
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/switch-cashier', [
                'admin_id' => $this->manager->id,
                'pin' => '9999',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pin');

        $this->assertAuthenticatedAs($this->cashier, 'admin');

        $switchResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/switch-cashier', [
                'admin_id' => $this->manager->id,
                'pin' => '2222',
            ]);

        $switchResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cashier.id', $this->manager->id);

        $this->assertAuthenticatedAs($this->manager, 'admin');
    }

    public function test_pos_requires_open_shift_and_tracks_cash_split_sales_and_close_variance(): void
    {
        $product = Product::factory()->create([
            'name' => 'Basmati Rice 10lb',
            'price' => 15.00,
            'stock' => 20,
            'is_taxable' => false,
        ]);

        // Attempt sale before opening shift
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'cash_tendered' => 20.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shift');

        // Open shift with $200.00 float
        $openResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/shifts/open', [
                'opening_cash' => 200.00,
                'notes' => 'Morning counter float',
            ]);

        $openResponse->assertCreated()
            ->assertJsonPath('shift.opening_cash', 200)
            ->assertJsonPath('shift.expected_cash', 200);

        $shiftId = $openResponse->json('shift.id');

        // Duplicate open shift is blocked
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/shifts/open', ['opening_cash' => 100.00])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('opening_cash');

        // Sale 1: Cash ($15.00 total, $20.00 tendered => $5.00 change due)
        $cashSale = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'cash_tendered' => 20.00,
            ]);

        $cashSale->assertCreated()
            ->assertJsonPath('order.total', 15)
            ->assertJsonPath('order.change_due', 5)
            ->assertJsonPath('shift.cash_sales', 15)
            ->assertJsonPath('shift.expected_cash', 215);

        // Sale 2: Split Tender ($10.00 cash + $20.00 terminal = $30.00 total)
        $splitSale = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payment_method' => 'split',
                'cash_amount' => 10.00,
                'terminal_amount' => 20.00,
                'cash_tendered' => 10.00,
                'payment_reference' => 'TERM-8812',
            ]);

        $splitSale->assertCreated()
            ->assertJsonPath('order.total', 30)
            ->assertJsonPath('order.cash_amount', 10)
            ->assertJsonPath('order.terminal_amount', 20)
            ->assertJsonPath('shift.cash_sales', 25)
            ->assertJsonPath('shift.terminal_sales', 20)
            ->assertJsonPath('shift.expected_cash', 225)
            ->assertJsonPath('shift.orders_count', 2);

        // Close shift with $223.50 counted cash (-$1.50 short variance)
        $closeResponse = $this->actingAs($this->manager, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/close", [
                'counted_cash' => 223.50,
                'notes' => 'End of day count',
            ]);

        $closeResponse->assertOk()
            ->assertJsonPath('shift.status', 'closed')
            ->assertJsonPath('shift.expected_cash', 225)
            ->assertJsonPath('shift.counted_cash', 223.5)
            ->assertJsonPath('shift.cash_difference', -1.5);
    }

    public function test_pos_supports_weighed_produce_custom_items_and_negative_stock_while_online_blocks_zero_stock(): void
    {
        PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 150.00,
            'expected_cash' => 150.00,
        ]);

        $lowStockPack = Product::factory()->create([
            'name' => 'Haldiram Bhujia',
            'barcode' => '8904063200012',
            'price' => 4.50,
            'unit_type' => 'each',
            'stock' => 1,
            'is_taxable' => false,
        ]);

        $looseOkra = Product::factory()->create([
            'name' => 'Fresh Bhindi (Okra)',
            'sku' => '4066',
            'price' => 4.00,
            'unit_type' => 'lb',
            'stock' => 10,
            'is_taxable' => false,
        ]);

        // Weighed item without weight fails validation
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $looseOkra->id, 'quantity' => 1]],
                'payment_method' => 'pos_terminal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        // Ring 3x Haldiram (stock 1 -> -2!), 1.5 lb Okra ($6.00), and 1x Custom Samosa Box ($8.00)
        // Subtotal = (3 * 4.50) + (1.5 * 4.00) + 8.00 = 13.50 + 6.00 + 8.00 = $27.50
        $posResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [
                    ['product_id' => $lowStockPack->id, 'quantity' => 3],
                    ['product_id' => $looseOkra->id, 'quantity' => 1, 'weight' => 1.5],
                    ['name' => 'Fresh Hot Samosa Box', 'unit_price' => 8.00, 'quantity' => 1, 'is_taxable' => false],
                ],
                'payment_method' => 'pos_terminal',
                'payment_reference' => 'AUTH-5500',
            ]);

        $posResponse->assertCreated()
            ->assertJsonPath('order.channel', 'pos')
            ->assertJsonPath('order.customer_name', 'Walk-in Customer')
            ->assertJsonPath('order.customer_email', null)
            ->assertJsonPath('order.subtotal', 27.5)
            ->assertJsonPath('order.total', 27.5);

        $lowStockPack->refresh();
        $this->assertSame(-2, $lowStockPack->stock);
        $this->assertSame('Out of stock', $lowStockPack->stock_badge);

        // Online storefront checkout MUST still block ordering depleted stock (<= 0)
        $onlineShopper = User::factory()->create();
        $this->actingAs($onlineShopper)
            ->postJson('/checkout', [
                'items' => [
                    [
                        'id' => $lowStockPack->id,
                        'name' => $lowStockPack->name,
                        'price' => 4.50,
                        'quantity' => 1,
                    ],
                ],
                'payment_method' => 'apple-pay',
                'pickup_timing_mode' => 'asap',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_unified_sales_tax_applies_only_to_taxable_items_on_both_pos_and_online_checkout(): void
    {
        StoreSetting::current()->update(['tax_rate' => 13.000]);

        PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 100.00,
            'expected_cash' => 100.00,
        ]);

        $taxExemptAtta = Product::factory()->create([
            'name' => 'Chakki Fresh Atta',
            'price' => 20.00,
            'stock' => 30,
            'is_taxable' => false,
        ]);

        $taxableSnack = Product::factory()->create([
            'name' => 'Masala Chai Ready Cup',
            'price' => 10.00,
            'stock' => 30,
            'is_taxable' => true,
        ]);

        // 1. POS Checkout: $20 exempt + $10 taxable @ 13% = $1.30 tax, $31.30 total
        $posResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [
                    ['product_id' => $taxExemptAtta->id, 'quantity' => 1],
                    ['product_id' => $taxableSnack->id, 'quantity' => 1],
                ],
                'payment_method' => 'pos_terminal',
            ]);

        $posResponse->assertCreated()
            ->assertJsonPath('order.subtotal', 30)
            ->assertJsonPath('order.tax', 1.3)
            ->assertJsonPath('order.total', 31.3);

        // 2. Online Checkout: exact same items => $1.30 tax, $31.30 total
        $onlineUser = User::factory()->create();
        $onlineResponse = $this->actingAs($onlineUser)
            ->postJson('/checkout', [
                'items' => [
                    [
                        'id' => $taxExemptAtta->id,
                        'name' => $taxExemptAtta->name,
                        'price' => 20.00,
                        'quantity' => 1,
                    ],
                    [
                        'id' => $taxableSnack->id,
                        'name' => $taxableSnack->name,
                        'price' => 10.00,
                        'quantity' => 1,
                    ],
                ],
                'payment_method' => 'apple-pay',
                'pickup_timing_mode' => 'asap',
            ]);

        $onlineResponse->assertCreated();
        $this->assertDatabaseHas('orders', [
            'user_id' => $onlineUser->id,
            'channel' => 'online',
            'subtotal' => 30.00,
            'tax' => 1.30,
            'total' => 31.30,
        ]);
    }

    public function test_pos_customer_quick_create_phone_search_and_loyalty_500_points_redemption(): void
    {
        PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 100.00,
            'expected_cash' => 100.00,
        ]);

        // Quick-create walk-in loyalty customer using only Name + Phone
        $createCust = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/customers', [
                'name' => 'Meera Nair',
                'phone' => '(555) 888-9911',
            ]);

        $createCust->assertCreated()
            ->assertJsonPath('customer.name', 'Meera Nair')
            ->assertJsonPath('customer.email', null);

        $customerId = $createCust->json('customer.id');

        // Search by raw digits matches formatted phone number
        $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/customers/search?q=5558889911')
            ->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.id', $customerId);

        // Every new customer starts with 100 welcome bonus points; add 550 more => 650 total
        $customer = User::findOrFail($customerId);
        $this->assertSame(100, $customer->reward_points);

        RewardPoint::create([
            'user_id' => $customer->id,
            'points' => 550,
            'description' => 'Loyalty bonus credit',
        ]);
        $customer->refreshRewardPointsBalance();
        $this->assertSame(650, $customer->fresh()->reward_points);

        $product = Product::factory()->create([
            'price' => 25.00,
            'stock' => 15,
            'is_taxable' => false,
        ]);

        // Redeem 500 pts ($10.00 off) on $25.00 basket => $15.00 total, earns 15 pts => 650 - 500 + 15 = 165 pts
        $saleResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'customer_id' => $customer->id,
                'points_to_redeem' => 500,
                'payment_method' => 'pos_terminal',
            ]);

        $saleResponse->assertCreated()
            ->assertJsonPath('order.subtotal', 25)
            ->assertJsonPath('order.points_redeemed', 500)
            ->assertJsonPath('order.points_discount', 10)
            ->assertJsonPath('order.total', 15)
            ->assertJsonPath('order.points_earned', 15)
            ->assertJsonPath('customer.reward_points', 165);

        $this->assertSame(165, $customer->fresh()->reward_points);
    }

    public function test_pos_returns_handle_restock_vs_damaged_waste_shift_drawer_and_reward_points(): void
    {
        $shift = PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 200.00,
            'expected_cash' => 200.00,
        ]);

        $customer = User::factory()->create(['phone' => '555-222-3344']);

        $jarGhee = Product::factory()->create([
            'name' => 'Pure Cow Ghee',
            'price' => 10.00,
            'stock' => 10,
            'is_taxable' => false,
        ]);

        $freshPaneer = Product::factory()->create([
            'name' => 'Malai Paneer',
            'price' => 8.00,
            'stock' => 5,
            'is_taxable' => false,
        ]);

        // Purchase 2x Ghee ($20) + 1x Paneer ($8) = $28.00 Cash
        $sale = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [
                    ['product_id' => $jarGhee->id, 'quantity' => 2],
                    ['product_id' => $freshPaneer->id, 'quantity' => 1],
                ],
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'cash_tendered' => 28.00,
            ])
            ->assertCreated();

        $orderId = $sale->json('order.id');
        $gheeItemId = collect($sale->json('order.items'))->firstWhere('product_id', $jarGhee->id)['id'];
        $paneerItemId = collect($sale->json('order.items'))->firstWhere('product_id', $freshPaneer->id)['id'];

        $this->assertSame(8, $jarGhee->fresh()->stock);
        $this->assertSame(4, $freshPaneer->fresh()->stock);
        // 100 welcome bonus + 28 earned = 128
        $this->assertSame(128, $customer->fresh()->reward_points);
        $this->assertEquals(228.00, (float) $shift->fresh()->expected_cash);

        // Return 1: 1x Ghee ($10.00) via Cash with restock = true => stock 8 -> 9, drawer 228 -> 218, points 128 -> 118
        $returnRestock = $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$orderId}/refund", [
                'items' => [
                    ['order_item_id' => $gheeItemId, 'quantity' => 1, 'restock' => true],
                ],
                'refund_method' => 'cash',
                'reason' => 'Unopened return',
            ]);

        $returnRestock->assertOk()
            ->assertJsonPath('refund_amount', 10)
            ->assertJsonPath('shift.cash_refunds', 10)
            ->assertJsonPath('shift.expected_cash', 218);

        $this->assertSame(9, $jarGhee->fresh()->stock);
        $this->assertSame(118, $customer->fresh()->reward_points);

        // Return 2: 1x Paneer ($8.00) via Terminal with restock = false (Damaged/Waste) => stock stays 4!
        $returnWaste = $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$orderId}/refund", [
                'items' => [
                    ['order_item_id' => $paneerItemId, 'quantity' => 1, 'restock' => false],
                ],
                'refund_method' => 'pos_terminal',
                'reason' => 'Opened package',
            ]);

        $returnWaste->assertOk()
            ->assertJsonPath('refund_amount', 8)
            ->assertJsonPath('shift.cash_refunds', 10)
            ->assertJsonPath('shift.terminal_refunds', 8)
            ->assertJsonPath('shift.expected_cash', 218);

        $this->assertSame(4, $freshPaneer->fresh()->stock);
        $this->assertSame(110, $customer->fresh()->reward_points);
    }

    public function test_pos_subdomain_routing_resolves_root_register_when_pos_domain_configured(): void
    {
        // Verify PosRequest helper recognizes configured POS_DOMAIN hosts
        config(['pos.domain' => 'pos.masalamart.test']);

        $subdomainRequest = Request::create('https://pos.masalamart.test/login', 'GET');
        $storefrontRequest = Request::create('https://masalamart.test/pos/login', 'GET');

        $this->assertTrue(PosRequest::matches($subdomainRequest));
        $this->assertFalse(PosRequest::matches($storefrontRequest));
    }

    public function test_admin_can_configure_pos_product_fields_store_tax_rate_and_staff_pin(): void
    {
        $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.admins.pin.update', $this->cashier->id), [
                'pos_pin' => '7890',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('7890', (string) $this->cashier->fresh()->pos_pin));

        $setting = StoreSetting::current();
        $this->actingAs($this->superAdmin, 'admin')
            ->put(route('admin.store-info.update'), [
                'name' => $setting->name,
                'address' => $setting->address,
                'city' => $setting->city,
                'state' => $setting->state,
                'zip' => $setting->zip,
                'phone' => $setting->phone,
                'email' => $setting->email,
                'opening_hours' => $setting->opening_hours,
                'tax_rate' => 8.875,
            ])
            ->assertRedirect();

        $this->assertEquals(8.875, StoreSetting::current()->tax_rate);
    }

    public function test_pos_register_and_orders_inertia_pages_render_with_categories_shift_and_cashiers(): void
    {
        PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 200.00,
            'expected_cash' => 200.00,
        ]);

        Product::factory()->create([
            'name' => 'Amul Butter 500g',
            'category' => 'dairy',
            'barcode' => '8901262010016',
            'sku' => 'AMUL-500',
            'price' => 6.49,
            'unit_type' => 'each',
        ]);

        Product::factory()->create([
            'name' => 'Fresh Okra (Bhindi)',
            'category' => 'produce',
            'sku' => '4066',
            'price' => 2.99,
            'unit_type' => 'lb',
        ]);

        $this->actingAs($this->cashier, 'admin')
            ->get('/pos')
            ->assertOk()
            ->assertViewIs('pos')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pos/Register')
                ->missing('products')
                ->where('catalogSummary.total_products', 2)
                ->where('catalogSummary.weighed_products', 1)
                ->where('currentShift.opening_cash', 200)
                ->has('categories')
                ->has('cashiers', 3)
            );

        $this->actingAs($this->cashier, 'admin')
            ->get('/pos/orders')
            ->assertOk()
            ->assertViewIs('pos')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pos/Orders')
                ->missing('products')
                ->where('initialTab', 'orders')
            );
    }

    public function test_pos_catalog_api_loads_products_on_demand_by_category_scale_filter_search_and_pagination(): void
    {
        Category::updateOrCreate(
            ['slug' => 'dairy'],
            ['name' => 'Dairy, Paneer & Ghee', 'is_active' => true, 'sort_order' => 1],
        );

        $butter = Product::factory()->create([
            'name' => 'Amul Butter 500g',
            'category' => 'dairy',
            'barcode' => '8901262010016',
            'sku' => 'AMUL-500',
            'price' => 6.49,
            'unit_type' => 'each',
        ]);

        $paneer = Product::factory()->create([
            'name' => 'Malai Paneer Block',
            'category' => 'dairy',
            'barcode' => '8901262010023',
            'sku' => 'PAN-400',
            'price' => 5.99,
            'unit_type' => 'each',
        ]);

        $okra = Product::factory()->create([
            'name' => 'Fresh Okra (Bhindi)',
            'category' => 'produce',
            'barcode' => null,
            'sku' => '4066',
            'price' => 2.99,
            'unit_type' => 'lb',
        ]);

        $uncategorized = Product::factory()->create([
            'name' => 'Festival Gift Basket',
            'category' => '',
            'sku' => 'GIFT-01',
            'price' => 29.99,
            'unit_type' => 'each',
        ]);

        // 1. Category drill-down returns only Dairy products
        $dairyResponse = $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products?category=dairy');

        $dairyResponse->assertOk()
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('products.0.id', $butter->id)
            ->assertJsonPath('products.1.id', $paneer->id);

        // 2. Scale / Weighed filter returns only loose lb/kg produce
        $weighedResponse = $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products?weighed=1');

        $weighedResponse->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $okra->id)
            ->assertJsonPath('products.0.unit_type', 'lb');

        // 3. Uncategorized fallback slug reaches products with empty category
        $uncategorizedResponse = $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products?category=__uncategorized__');

        $uncategorizedResponse->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $uncategorized->id);

        // 4. Search query matches PLU/SKU or partial name and paginates cleanly
        $paginatedAll = $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products?per_page=2&page=1');

        $paginatedAll->assertOk()
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.has_more', true);

        $pluSearch = $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products?q=4066');

        $pluSearch->assertOk()
            ->assertJsonPath('products.0.id', $okra->id);
    }

    public function test_pos_scan_endpoint_resolves_barcode_and_case_insensitive_plu_and_rejects_unauthorized_staff(): void
    {
        $butter = Product::factory()->create([
            'name' => 'Amul Butter 500g',
            'barcode' => '8901262010016',
            'sku' => 'AMUL-500',
            'price' => 6.49,
        ]);

        // Exact EAN-13 barcode scan
        $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products/scan?code=8901262010016')
            ->assertOk()
            ->assertJsonPath('product.id', $butter->id);

        // Case-insensitive SKU / PLU scan
        $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products/scan?code=amul-500')
            ->assertOk()
            ->assertJsonPath('product.id', $butter->id);

        // Unknown barcode returns 404
        $this->actingAs($this->cashier, 'admin')
            ->getJson('/pos/products/scan?code=0000009999999')
            ->assertNotFound()
            ->assertJsonPath('product', null);

        // Staff without operate pos permission is forbidden
        $packer = Admin::create([
            'name' => 'Warehouse Packer',
            'email' => 'warehouse@masalamart.com',
            'password' => Hash::make('password'),
        ]);
        $packer->assignRole('Order Handler');

        $this->actingAs($packer, 'admin')
            ->getJson('/pos/products')
            ->assertForbidden();

        $this->actingAs($packer, 'admin')
            ->getJson('/pos/products/scan?code=8901262010016')
            ->assertForbidden();
    }

    public function test_pos_safeguards_block_online_refunds_unshifted_refunds_negative_drawer_and_price_drift(): void
    {
        $product = Product::factory()->create([
            'name' => 'Royal Basmati 20lb',
            'price' => 25.00,
            'stock' => 20,
            'is_taxable' => false,
        ]);

        // 1. Online order cannot be refunded via POS
        $onlineUser = User::factory()->create();
        $onlineOrder = Order::create([
            'channel' => 'online',
            'user_id' => $onlineUser->id,
            'customer_name' => $onlineUser->name,
            'customer_email' => $onlineUser->email,
            'subtotal' => 25.00,
            'delivery_fee' => 5.00,
            'total' => 30.00,
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_test_online_123',
            'status' => 'confirmed',
        ]);
        $onlineItem = $onlineOrder->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 1,
            'total_price' => 25.00,
        ]);

        $shift = PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 10.00,
            'expected_cash' => 10.00,
        ]);

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$onlineOrder->id}/refund", [
                'items' => [['order_item_id' => $onlineItem->id, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'cash',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        // 2. Price / tax drift guard rejects mismatch between screen total and live server total
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'pos_terminal',
                'expected_total' => 20.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('total');

        // 3. Split tender rejects even a 1-cent mismatch instead of silently altering terminal amount
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'split',
                'cash_amount' => 10.00,
                'terminal_amount' => 14.99,
                'expected_total' => 25.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        // 4. Create valid $25.00 terminal sale on the $10.00 float shift
        $posSale = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'pos_terminal',
                'expected_total' => 25.00,
            ])
            ->assertCreated();

        $posOrderId = $posSale->json('order.id');
        $posItemId = $posSale->json('order.items.0.id');

        // Cash refund of $25.00 exceeds $10.00 expected drawer cash -> blocked!
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$posOrderId}/refund", [
                'items' => [['order_item_id' => $posItemId, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'cash',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_method');

        $this->assertEquals(10.00, (float) $shift->fresh()->expected_cash);

        // Close shift, then verify even Card Terminal refunds require an open shift
        $shift->closeShift($this->cashier, 10.00);

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$posOrderId}/refund", [
                'items' => [['order_item_id' => $posItemId, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'pos_terminal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shift');

        // Reopen shift => Card Terminal refund succeeds and records on the shift
        $newShift = PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 10.00,
            'expected_cash' => 10.00,
        ]);

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$posOrderId}/refund", [
                'items' => [['order_item_id' => $posItemId, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'pos_terminal',
            ])
            ->assertOk()
            ->assertJsonPath('refund_amount', 25)
            ->assertJsonPath('shift.id', $newShift->id)
            ->assertJsonPath('shift.terminal_refunds', 25);

        // 5. Pending unpaid POS order cannot be refunded
        $pendingPosOrder = Order::create([
            'channel' => 'pos',
            'admin_id' => $this->cashier->id,
            'customer_name' => 'Walk-in',
            'subtotal' => 25.00,
            'total' => 25.00,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);
        $pendingPosItem = $pendingPosOrder->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 25.00,
            'quantity' => 1,
            'total_price' => 25.00,
        ]);

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$pendingPosOrder->id}/refund", [
                'items' => [['order_item_id' => $pendingPosItem->id, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'pos_terminal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        // 6. Catalog ids[] lookup returns fresh pricing/taxability for cart drift sync
        $otherProduct = Product::factory()->create(['price' => 9.50]);
        $this->actingAs($this->cashier, 'admin')
            ->getJson("/pos/products?ids[]={$product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonMissing(['id' => $otherProduct->id]);
    }

    public function test_pos_idempotency_key_and_loyalty_floor_cap_prevent_double_charge_and_point_loss(): void
    {
        $shift = PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 100.00,
            'expected_cash' => 100.00,
        ]);

        $snack = Product::factory()->create([
            'name' => 'Samosa Pack',
            'price' => 6.00,
            'stock' => 20,
            'is_taxable' => false,
        ]);

        $customer = User::factory()->create();
        RewardPoint::create([
            'user_id' => $customer->id,
            'points' => 500,
            'description' => 'Loyalty balance',
        ]);
        $customer->refreshRewardPointsBalance();
        // 100 welcome bonus + 500 = 600 points
        $this->assertSame(600, $customer->fresh()->reward_points);

        // Attempt to redeem 500 pts ($10 block) on a $6.00 basket => floor caps usable blocks at 0 (no wasted points!)
        $saleOne = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'idempotency_key' => 'pos-retry-uuid-001',
                'expected_total' => 6.00,
                'items' => [['product_id' => $snack->id, 'quantity' => 1]],
                'customer_id' => $customer->id,
                'points_to_redeem' => 500,
                'payment_method' => 'cash',
                'cash_tendered' => 10.00,
            ]);

        $saleOne->assertCreated()
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('order.points_redeemed', 0)
            ->assertJsonPath('order.points_discount', 0)
            ->assertJsonPath('order.total', 6)
            ->assertJsonPath('customer.reward_points', 606);

        $firstOrderId = $saleOne->json('order.id');
        $this->assertSame(19, $snack->fresh()->stock);
        $this->assertEquals(106.00, (float) $shift->fresh()->expected_cash);

        // Retry exact same idempotency_key => replays original order with zero duplicate charge or stock deduction
        $saleReplay = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'idempotency_key' => 'pos-retry-uuid-001',
                'expected_total' => 6.00,
                'items' => [['product_id' => $snack->id, 'quantity' => 1]],
                'customer_id' => $customer->id,
                'points_to_redeem' => 500,
                'payment_method' => 'cash',
                'cash_tendered' => 10.00,
            ]);

        $saleReplay->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('order.id', $firstOrderId);

        $this->assertSame(1, Order::where('idempotency_key', 'pos-retry-uuid-001')->count());
        $this->assertSame(19, $snack->fresh()->stock);
        $this->assertEquals(106.00, (float) $shift->fresh()->expected_cash);
        $this->assertSame(1, $shift->fresh()->orders_count);

        // Even if the shift was closed right after creation before the retry arrived, replay still succeeds
        $shift->closeShift($this->cashier, 106.00);
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'idempotency_key' => 'pos-retry-uuid-001',
                'expected_total' => 6.00,
                'items' => [['product_id' => $snack->id, 'quantity' => 1]],
                'customer_id' => $customer->id,
                'points_to_redeem' => 500,
                'payment_method' => 'cash',
                'cash_tendered' => 10.00,
            ])
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('order.id', $firstOrderId);
    }

    public function test_pos_cash_drawer_deposits_safe_drops_and_shift_reconciliation(): void
    {
        $shift = PosShift::factory()->create([
            'admin_id' => $this->cashier->id,
            'opening_cash' => 100.00,
            'expected_cash' => 100.00,
        ]);

        // Unauthenticated and non-POS staff cannot alter cash drawer
        $this->postJson("/pos/shifts/{$shift->id}/movements", [
            'type' => 'deposit',
            'amount' => 50.00,
            'reason' => 'Coins & Change Top-Up',
        ])->assertUnauthorized();

        $packer = Admin::create([
            'name' => 'Warehouse Packer',
            'email' => 'packer-drawer@masalamart.com',
            'password' => Hash::make('password'),
        ]);
        $packer->assignRole('Order Handler');

        $this->actingAs($packer, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/movements", [
                'type' => 'deposit',
                'amount' => 50.00,
                'reason' => 'Coins & Change Top-Up',
            ])
            ->assertForbidden();

        // 1. Out of coins / change -> cashier deposits $50.00 Pay-In
        $depositResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/movements", [
                'type' => 'deposit',
                'amount' => 50.00,
                'reason' => 'Coins & Change Top-Up',
                'notes' => 'Two quarter rolls + $1 bills',
            ]);

        $depositResponse->assertCreated()
            ->assertJsonPath('shift.cash_deposits', 50)
            ->assertJsonPath('shift.expected_cash', 150)
            ->assertJsonPath('shift.safe_drop_recommended', false)
            ->assertJsonPath('movement.type', PosCashMovement::TYPE_DEPOSIT)
            ->assertJsonPath('movement.balance_after', 150)
            ->assertJsonPath('shift.movements.0.reason', 'Coins & Change Top-Up');

        // 2. Busy cash sales push drawer over $1,000 threshold -> soft Safe Drop alert triggers without blocking sale
        $bulkItem = Product::factory()->create([
            'name' => 'Catering Rice Sack Bundle',
            'price' => 950.00,
            'stock' => 10,
            'is_taxable' => false,
        ]);

        $saleResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'items' => [['product_id' => $bulkItem->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'cash_tendered' => 950.00,
                'expected_total' => 950.00,
            ]);

        // $100 opening + $50 deposit + $950 cash sale = $1,100 expected cash -> safe_drop_recommended true
        $saleResponse->assertCreated()
            ->assertJsonPath('shift.expected_cash', 1100)
            ->assertJsonPath('shift.cash_deposits', 50)
            ->assertJsonPath('shift.safe_drop_recommended', true);

        // 3. Cashier withdraws $900.00 Safe Drop to bank safe -> leaves $200.00 float, clears alert
        $dropResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/movements", [
                'type' => 'withdrawal',
                'amount' => 900.00,
                'reason' => 'Safe Drop / Bank Bag',
                'notes' => 'Envelope #12 dropped in office safe',
            ]);

        $dropResponse->assertCreated()
            ->assertJsonPath('shift.cash_withdrawals', 900)
            ->assertJsonPath('shift.expected_cash', 200)
            ->assertJsonPath('shift.safe_drop_recommended', false)
            ->assertJsonPath('movement.type', PosCashMovement::TYPE_WITHDRAWAL)
            ->assertJsonPath('movement.balance_after', 200);

        // 4. Attempting to withdraw more than available expected cash is blocked
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/movements", [
                'type' => 'withdrawal',
                'amount' => 200.01,
                'reason' => 'Excess Cash Pickup',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertEquals(200.00, (float) $shift->fresh()->expected_cash);

        // 5. Validation rejects empty reason and non-positive amount
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/movements", [
                'type' => 'deposit',
                'amount' => 0,
                'reason' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'reason']);

        // 6. Shift Z-Report reconciliation balances cleanly at $200.00 and blocks further adjustments once closed
        $closeResponse = $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/close", [
                'counted_cash' => 200.00,
                'notes' => 'End of shift balanced with $900 drop',
            ]);

        $closeResponse->assertOk()
            ->assertJsonPath('shift.expected_cash', 200)
            ->assertJsonPath('shift.counted_cash', 200)
            ->assertJsonPath('shift.cash_difference', 0)
            ->assertJsonPath('shift.cash_deposits', 50)
            ->assertJsonPath('shift.cash_withdrawals', 900);

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift->id}/movements", [
                'type' => 'deposit',
                'amount' => 10.00,
                'reason' => 'Late Coins',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shift');

        $this->assertSame(2, PosCashMovement::where('pos_shift_id', $shift->id)->count());
    }

    public function test_pos_barcode_scan_resolves_leading_zero_upc_ean_variants_and_alternate_barcodes(): void
    {
        $upcProduct = Product::factory()->create([
            'name' => 'Deep Sooji Coarse Flour 2lb',
            'barcode' => '011433112802',
            'price' => 3.49,
        ]);

        $dualBarcodeProduct = Product::factory()->create([
            'name' => 'Kurkure Masala Munch 90g',
            'barcode' => '8901491100519',
            'alternate_barcodes' => ['060410010402'],
            'price' => 1.29,
        ]);

        // 1. Exact 12-digit UPC-A scan
        $this->actingAs($this->cashier, 'admin')
            ->getJson(route('pos.products.scan', ['code' => '011433112802']))
            ->assertOk()
            ->assertJsonPath('product.id', $upcProduct->id);

        // 2. Scanner emitting 13-digit EAN-13 (0-prefixed UPC-A) or stripped 11-digit code
        $this->actingAs($this->cashier, 'admin')
            ->getJson(route('pos.products.scan', ['code' => '0011433112802']))
            ->assertOk()
            ->assertJsonPath('product.id', $upcProduct->id);

        $this->actingAs($this->cashier, 'admin')
            ->getJson(route('pos.products.scan', ['code' => '11433112802']))
            ->assertOk()
            ->assertJsonPath('product.id', $upcProduct->id);

        // 3. Multi-barcode packet scanned via Indian EAN-13 OR Canadian importer UPC-A (with or without leading 0)
        $this->actingAs($this->cashier, 'admin')
            ->getJson(route('pos.products.scan', ['code' => '8901491100519']))
            ->assertOk()
            ->assertJsonPath('product.id', $dualBarcodeProduct->id);

        $this->actingAs($this->cashier, 'admin')
            ->getJson(route('pos.products.scan', ['code' => '060410010402']))
            ->assertOk()
            ->assertJsonPath('product.id', $dualBarcodeProduct->id);

        $this->actingAs($this->cashier, 'admin')
            ->getJson(route('pos.products.scan', ['code' => '60410010402']))
            ->assertOk()
            ->assertJsonPath('product.id', $dualBarcodeProduct->id);
    }
}
