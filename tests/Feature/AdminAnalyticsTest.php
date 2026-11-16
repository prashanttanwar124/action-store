<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\StoreSetting;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
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

    public function test_analytics_route_and_dashboard_snapshot_enforce_view_reports_permission(): void
    {
        $this->actingAs($this->cashier, 'admin')
            ->get('/admin/analytics')
            ->assertForbidden();

        $this->actingAs($this->cashier, 'admin')
            ->get('/admin/analytics/export')
            ->assertForbidden();

        $this->actingAs($this->cashier, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('paymentSnapshot', null),
            );

        $this->actingAs($this->manager, 'admin')
            ->get('/admin/analytics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->has('report.summary')
                ->has('report.series')
                ->has('report.shifts'),
            );

        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('paymentSnapshot.summary.total_net', 0),
            );
    }

    public function test_analytics_accurately_reconciles_pos_cash_card_split_online_refunds_and_drawer_movements(): void
    {
        StoreSetting::current()->update(['tax_rate' => 0.00]);

        $product = Product::factory()->create([
            'price' => 10.00,
            'stock' => 100,
            'unit_type' => 'each',
            'is_taxable' => false,
        ]);

        // 1. Open POS Shift ($200 opening float), deposit $50 coins
        $shiftId = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/shifts/open', ['opening_cash' => 200.00])
            ->assertCreated()
            ->json('shift.id');

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/movements", [
                'type' => 'deposit',
                'amount' => 50.00,
                'reason' => 'Coins & Change Top-Up',
            ])
            ->assertCreated();

        // 2. POS Cash sale: $40.00 (customer tenders $50.00, change $10.00 -> cash_amount must be $40.00)
        $cashOrderId = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'cash',
                'cash_tendered' => 50.00,
                'items' => [['product_id' => $product->id, 'quantity' => 4]],
            ])
            ->assertCreated()
            ->json('order.id');

        // 3. POS Card Terminal sale: $60.00
        $cardOrderId = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'pos_terminal',
                'items' => [['product_id' => $product->id, 'quantity' => 6]],
            ])
            ->assertCreated()
            ->json('order.id');

        // 4. POS Split sale: $50.00 ($20.00 Cash + $30.00 Card)
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'split',
                'cash_amount' => 20.00,
                'terminal_amount' => 30.00,
                'cash_tendered' => 20.00,
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ])
            ->assertCreated();

        // 5. Safe Drop $40.00
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/movements", [
                'type' => 'withdrawal',
                'amount' => 40.00,
                'reason' => 'Safe Drop / Bank Bag',
            ])
            ->assertCreated();

        // 6. Refund 1 item ($10.00) via Cash Drawer on the Cash order, and 1.5 ($10 + $5 via Card order qty 1 = $10? Use qty 2 = $20 or card order qty 1 = $10 + split)
        $cashItem = OrderItem::where('order_id', $cashOrderId)->firstOrFail();
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$cashOrderId}/refund", [
                'items' => [['order_item_id' => $cashItem->id, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'cash',
            ])
            ->assertOk();

        $cardItem = OrderItem::where('order_id', $cardOrderId)->firstOrFail();
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$cardOrderId}/refund", [
                'items' => [['order_item_id' => $cardItem->id, 'quantity' => 2, 'restock' => true]],
                'refund_method' => 'pos_terminal',
            ])
            ->assertOk();

        // 7. Online Website orders:
        //    - $100.00 confirmed online order with $25.00 partial OOS Stripe refund
        Order::factory()->create([
            'channel' => 'online',
            'status' => 'confirmed',
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_online_active',
            'subtotal' => 90.00,
            'tax' => 5.00,
            'delivery_fee' => 5.00,
            'total' => 100.00,
            'refunded_total' => 25.00,
        ]);

        //    - $80.00 cancelled online order fully refunded via Stripe
        Order::factory()->create([
            'channel' => 'online',
            'status' => 'cancelled',
            'payment_method' => 'apple-pay',
            'stripe_payment_id' => 'pi_online_cancelled',
            'subtotal' => 80.00,
            'tax' => 0.00,
            'delivery_fee' => 0.00,
            'total' => 80.00,
            'refunded_total' => 80.00,
        ]);

        // 8. Unpaid pending order must NOT affect analytics
        Order::factory()->create([
            'channel' => 'pos',
            'status' => 'pending',
            'payment_method' => 'cash',
            'total' => 999.00,
            'refunded_total' => 0.00,
        ]);

        $this->actingAs($this->manager, 'admin')
            ->get('/admin/analytics?preset=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                // POS Cash: Gross 40 + 20 = 60, Refunds = 10, Net = 50
                ->where('report.summary.pos_cash_gross', 60)
                ->where('report.summary.pos_cash_refunds', 10)
                ->where('report.summary.pos_cash_net', 50)
                // POS Card: Gross 60 + 30 = 90, Refunds = 20, Net = 70
                ->where('report.summary.pos_card_gross', 90)
                ->where('report.summary.pos_card_refunds', 20)
                ->where('report.summary.pos_card_net', 70)
                // Online: Gross 100 + 80 = 180, Refunds 25 + 80 = 105, Net = 75
                ->where('report.summary.online_gross', 180)
                ->where('report.summary.online_refunds', 105)
                ->where('report.summary.online_net', 75)
                // Grand Totals: Gross 330, Refunds 135, Net 195, Paid Orders = 5 (pending excluded)
                ->where('report.summary.total_gross', 330)
                ->where('report.summary.total_refunds', 135)
                ->where('report.summary.total_net', 195)
                ->where('report.summary.total_orders', 5)
                ->where('report.summary.pos_split_orders', 1)
                // Drawer summary: 200 opening + 50 deposit + 60 cash sales - 10 cash refund - 40 safe drop = 260 expected
                ->where('report.drawer_summary.cash_deposits_total', 50)
                ->where('report.drawer_summary.cash_withdrawals_total', 40)
                ->where('report.drawer_summary.expected_cash_total', 260)
                ->where('report.integrity.is_healthy', true),
            );
    }

    public function test_analytics_supports_day_week_month_grouping_custom_range_and_safe_csv_export(): void
    {
        $octShift = PosShift::create([
            'admin_id' => $this->cashier->id,
            'closed_by_admin_id' => $this->cashier->id,
            'status' => 'closed',
            'opening_cash' => 100.00,
            'cash_sales' => 50.00,
            'cash_deposits' => 0.00,
            'terminal_sales' => 75.00,
            'cash_refunds' => 0.00,
            'cash_withdrawals' => 0.00,
            'terminal_refunds' => 5.00,
            'expected_cash' => 150.00,
            'counted_cash' => 150.00,
            'cash_difference' => 0.00,
            'counted_terminal' => 70.00,
            'terminal_difference' => 0.00,
            'orders_count' => 2,
            'notes' => '=HYPERLINK("http://evil.test")',
            'opened_at' => '2026-10-10 09:00:00',
            'closed_at' => '2026-10-10 18:00:00',
        ]);

        Order::factory()->create([
            'channel' => 'pos',
            'status' => 'completed',
            'payment_method' => 'cash',
            'cash_amount' => 50.00,
            'terminal_amount' => 0.00,
            'total' => 50.00,
            'pos_shift_id' => $octShift->id,
            'created_at' => '2026-10-10 11:00:00',
        ]);

        Order::factory()->create([
            'channel' => 'pos',
            'status' => 'completed',
            'payment_method' => 'pos_terminal',
            'cash_amount' => 0.00,
            'terminal_amount' => 75.00,
            'total' => 75.00,
            'pos_shift_id' => $octShift->id,
            'created_at' => '2026-10-10 12:00:00',
        ]);

        Order::factory()->create([
            'channel' => 'online',
            'status' => 'completed',
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_nov_order',
            'total' => 120.00,
            'refunded_total' => 0.00,
            'created_at' => '2026-11-05 15:00:00',
        ]);

        // Reversed custom From/To should normalize cleanly, Monthly grouping produces Oct & Nov buckets
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/analytics?from=2026-11-12&to=2026-10-01&group_by=month')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.filters.from', '2026-10-01')
                ->where('report.filters.to', '2026-11-12')
                ->where('report.filters.group_by', 'month')
                ->has('report.series', 2)
                ->where('report.series.0.pos_cash_net', 50)
                ->where('report.series.0.pos_card_net', 70)
                ->where('report.series.1.online_net', 120)
                ->where('report.summary.total_net', 240),
            );

        // Weekly grouping works across the range
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/analytics?from=2026-10-05&to=2026-11-08&group_by=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.filters.group_by', 'week')
                ->where('report.summary.total_net', 240),
            );

        // Daily grouping produces continuous buckets (10 days -> 10 rows)
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/analytics?from=2026-10-05&to=2026-10-14&group_by=day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.filters.group_by', 'day')
                ->has('report.series', 10),
            );

        // CSV Export downloads clean structured totals & shift rows
        $exportResponse = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/analytics/export?from=2026-10-01&to=2026-11-12&group_by=month');

        $exportResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportResponse->headers->get('content-type'));
        $csvContent = $exportResponse->streamedContent();
        $this->assertStringContainsString('POS Cash Drawer', $csvContent);
        $this->assertStringContainsString('POS Card Terminal', $csvContent);
        $this->assertStringContainsString('Website Online (Stripe)', $csvContent);
        $this->assertStringContainsString('240.00', $csvContent);
    }

    public function test_admin_kds_blocks_direct_refund_and_cancel_of_pos_orders(): void
    {
        $posOrder = Order::factory()->create([
            'channel' => 'pos',
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'total' => 25.00,
            'refunded_total' => 0.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $posOrder->id,
            'name' => 'Garam Masala',
            'unit_price' => 25.00,
            'quantity' => 1,
            'total_price' => 25.00,
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/admin/orders/{$posOrder->id}/items/{$item->id}/refund", [
                'quantity' => 1,
                'reason' => 'Test override',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item');

        $this->actingAs($this->superAdmin, 'admin')
            ->patchJson("/admin/orders/{$posOrder->id}/status", [
                'status' => 'cancelled',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertEquals(0.00, (float) $posOrder->fresh()->refunded_total);
    }

    public function test_pos_shift_close_supports_card_terminal_summary_and_optional_batch_slip_check(): void
    {
        $product = Product::factory()->create([
            'price' => 20.00,
            'stock' => 50,
            'unit_type' => 'each',
            'is_taxable' => false,
        ]);

        $shiftId = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/shifts/open', ['opening_cash' => 150.00])
            ->assertCreated()
            ->json('shift.id');

        // Split order ($20 cash + $20 card)
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'split',
                'cash_amount' => 20.00,
                'terminal_amount' => 20.00,
                'cash_tendered' => 20.00,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        // Card-only order ($40) with 1-item ($20) card refund => net_terminal = 20 + 40 - 20 = 40.00
        $cardOrderId = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'pos_terminal',
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->json('order.id');

        $cardItem = OrderItem::where('order_id', $cardOrderId)->firstOrFail();
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/orders/{$cardOrderId}/refund", [
                'items' => [['order_item_id' => $cardItem->id, 'quantity' => 1, 'restock' => true]],
                'refund_method' => 'pos_terminal',
            ])
            ->assertOk();

        // Close shift with physical cash 170.00 and batch slip 39.50 (-0.50 variance)
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/close", [
                'counted_cash' => 170.00,
                'counted_terminal' => 39.50,
            ])
            ->assertOk()
            ->assertJsonPath('shift.terminal_sales', 60)
            ->assertJsonPath('shift.terminal_refunds', 20)
            ->assertJsonPath('shift.net_terminal', 40)
            ->assertJsonPath('shift.counted_terminal', 39.5)
            ->assertJsonPath('shift.terminal_difference', -0.5)
            ->assertJsonPath('shift.split_orders_count', 1)
            ->assertJsonPath('shift.net_cash_sales', 20)
            ->assertJsonPath('shift.net_total_sales', 60);

        // Second shift closed WITHOUT counted_terminal stays null and succeeds
        $shift2Id = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/shifts/open', ['opening_cash' => 100.00])
            ->assertCreated()
            ->json('shift.id');

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shift2Id}/close", [
                'counted_cash' => 100.00,
            ])
            ->assertOk()
            ->assertJsonPath('shift.counted_terminal', null)
            ->assertJsonPath('shift.terminal_difference', null);
    }
}
