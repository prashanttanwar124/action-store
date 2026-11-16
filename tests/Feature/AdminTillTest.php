<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\StoreSetting;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTillTest extends TestCase
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

    public function test_till_audit_routes_enforce_view_reports_permission(): void
    {
        $shift = PosShift::create([
            'admin_id' => $this->cashier->id,
            'status' => 'open',
            'opening_cash' => 150.00,
            'expected_cash' => 150.00,
            'opened_at' => now()->subHour(),
        ]);

        $this->actingAs($this->cashier, 'admin')
            ->get('/admin/tills')
            ->assertForbidden();

        $this->actingAs($this->cashier, 'admin')
            ->get('/admin/tills/export')
            ->assertForbidden();

        $this->actingAs($this->cashier, 'admin')
            ->getJson("/admin/tills/{$shift->id}")
            ->assertForbidden();

        $this->actingAs($this->cashier, 'admin')
            ->postJson("/admin/tills/{$shift->id}/close", [
                'counted_cash' => 150.00,
            ])
            ->assertForbidden();

        $this->actingAs($this->manager, 'admin')
            ->get('/admin/tills')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Tills/Index')
                ->where('openShift.id', $shift->id)
                ->has('tills.data', 1)
                ->has('summary')
                ->has('staffList'),
            );

        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/tills')
            ->assertOk();
    }

    public function test_till_timeline_displays_chronological_open_movements_orders_and_close_entries(): void
    {
        StoreSetting::current()->update(['tax_rate' => 0.00]);

        $product = Product::factory()->create([
            'price' => 15.00,
            'stock' => 100,
            'unit_type' => 'each',
            'is_taxable' => false,
        ]);

        // Open till at 14:00 with $150 float
        $shiftId = $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/shifts/open', ['opening_cash' => 150.00])
            ->assertCreated()
            ->json('shift.id');

        // Deposit $40 coins
        $this->travel(5)->minutes();
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/movements", [
                'type' => 'deposit',
                'amount' => 40.00,
                'reason' => 'Coins & Change Top-Up',
                'notes' => 'Two rolls of loonies',
            ])
            ->assertCreated();

        // POS Cash sale ($30), Card sale ($45), Split sale ($10 cash + $20 card), plus a pending order that must be excluded
        $this->travel(10)->minutes();
        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'cash',
                'cash_tendered' => 50.00,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'pos_terminal',
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ])
            ->assertCreated();

        $this->actingAs($this->cashier, 'admin')
            ->postJson('/pos/orders', [
                'payment_method' => 'split',
                'cash_amount' => 10.00,
                'terminal_amount' => 20.00,
                'cash_tendered' => 10.00,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        Order::factory()->create([
            'channel' => 'pos',
            'status' => 'pending',
            'payment_method' => 'cash',
            'total' => 99.00,
            'pos_shift_id' => $shiftId,
        ]);

        // Safe drop $30
        $this->travel(5)->minutes();
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/movements", [
                'type' => 'withdrawal',
                'amount' => 30.00,
                'reason' => 'Safe Drop / Bank Bag',
            ])
            ->assertCreated();

        // Expected cash = 150 + 40 deposit + 30 cash + 10 split cash - 30 drop = 200.00
        // Expected terminal = 45 + 20 = 65.00
        $this->actingAs($this->manager, 'admin')
            ->get('/admin/tills')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('openShift.id', $shiftId)
                ->where('openShift.opened_by.name', 'Counter Cashier')
                ->where('openShift.opening_cash', 150)
                ->where('openShift.cash_deposits', 40)
                ->where('openShift.cash_withdrawals', 30)
                ->where('openShift.expected_cash', 200)
                ->where('openShift.net_terminal', 65)
                ->where('openShift.deposits_count', 1)
                ->where('openShift.withdrawals_count', 1)
                ->where('openShift.movements_count', 2),
            );

        // Manager inspects open till timeline JSON
        $this->actingAs($this->manager, 'admin')
            ->getJson("/admin/tills/{$shiftId}")
            ->assertOk()
            ->assertJsonPath('till.id', $shiftId)
            ->assertJsonCount(3, 'timeline')
            ->assertJsonPath('timeline.0.kind', 'opened')
            ->assertJsonPath('timeline.0.amount', 150)
            ->assertJsonPath('timeline.1.kind', 'deposit')
            ->assertJsonPath('timeline.1.amount', 40)
            ->assertJsonPath('timeline.2.kind', 'withdrawal')
            ->assertJsonPath('timeline.2.amount', 30)
            ->assertJsonCount(3, 'orders');

        // Cashier closes till with physical cash 198.00 (-2.00 variance) and card batch 65.00 (0.00 variance)
        $this->travel(2)->hours();
        $this->actingAs($this->cashier, 'admin')
            ->postJson("/pos/shifts/{$shiftId}/close", [
                'counted_cash' => 198.00,
                'counted_terminal' => 65.00,
                'notes' => 'Short $2 coin roll',
            ])
            ->assertOk();

        $this->actingAs($this->manager, 'admin')
            ->getJson("/admin/tills/{$shiftId}")
            ->assertOk()
            ->assertJsonPath('till.status', 'closed')
            ->assertJsonPath('till.counted_cash', 198)
            ->assertJsonPath('till.cash_difference', -2)
            ->assertJsonPath('till.counted_terminal', 65)
            ->assertJsonPath('till.terminal_difference', 0)
            ->assertJsonCount(4, 'timeline')
            ->assertJsonPath('timeline.3.kind', 'closed')
            ->assertJsonPath('timeline.3.actor_name', 'Counter Cashier')
            ->assertJsonPath('timeline.3.amount', 198)
            ->assertJsonPath('timeline.3.variance', -2);
    }

    public function test_till_filters_reversed_custom_dates_open_spotlight_and_safe_csv_export(): void
    {
        $octBalanced = PosShift::create([
            'admin_id' => $this->cashier->id,
            'closed_by_admin_id' => $this->manager->id,
            'status' => 'closed',
            'opening_cash' => 100.00,
            'cash_sales' => 80.00,
            'cash_deposits' => 0.00,
            'terminal_sales' => 50.00,
            'cash_refunds' => 0.00,
            'cash_withdrawals' => 0.00,
            'terminal_refunds' => 0.00,
            'expected_cash' => 180.00,
            'counted_cash' => 180.00,
            'cash_difference' => 0.00,
            'counted_terminal' => 50.00,
            'terminal_difference' => 0.00,
            'orders_count' => 3,
            'notes' => '=SUM(A1:A2)',
            'opened_at' => '2026-10-10 09:00:00',
            'closed_at' => '2026-10-10 17:30:00',
        ]);

        $octVariance = PosShift::create([
            'admin_id' => $this->manager->id,
            'closed_by_admin_id' => $this->manager->id,
            'status' => 'closed',
            'opening_cash' => 200.00,
            'cash_sales' => 100.00,
            'cash_deposits' => 25.00,
            'terminal_sales' => 60.00,
            'cash_refunds' => 0.00,
            'cash_withdrawals' => 50.00,
            'terminal_refunds' => 0.00,
            'expected_cash' => 275.00,
            'counted_cash' => 270.00,
            'cash_difference' => -5.00,
            'counted_terminal' => 60.00,
            'terminal_difference' => 0.00,
            'orders_count' => 4,
            'notes' => 'Missing $5 bill',
            'opened_at' => '2026-10-15 08:30:00',
            'closed_at' => '2026-10-15 16:00:00',
        ]);

        $novOpen = PosShift::create([
            'admin_id' => $this->cashier->id,
            'status' => 'open',
            'opening_cash' => 150.00,
            'expected_cash' => 150.00,
            'opened_at' => now()->subHours(2),
        ]);

        // Reversed custom date range for October with status=variance should find only octVariance, yet preserve live openShift
        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/tills?from=2026-10-31&to=2026-10-01&status=variance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-10-01')
                ->where('filters.to', '2026-10-31')
                ->where('openShift.id', $novOpen->id)
                ->has('tills.data', 1)
                ->where('tills.data.0.id', $octVariance->id)
                ->where('summary.variance_tills', 1),
            );

        // Staff filter + search across closed shifts
        $this->actingAs($this->superAdmin, 'admin')
            ->get("/admin/tills?preset=all&admin_id={$this->cashier->id}&status=closed")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tills.data', 1)
                ->where('tills.data.0.id', $octBalanced->id),
            );

        $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/tills?preset=all&search=Missing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tills.data', 1)
                ->where('tills.data.0.id', $octVariance->id),
            );

        // CSV Export downloads cleanly with formula injection escaped
        $export = $this->actingAs($this->superAdmin, 'admin')
            ->get('/admin/tills/export?from=2026-10-01&to=2026-10-31');

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Opening Float Entered', $csv);
        $this->assertStringContainsString('Counter Cashier', $csv);
        $this->assertStringContainsString("'=SUM(A1:A2)", $csv);
        $this->assertStringNotContainsString(',=SUM(A1:A2)', $csv);
    }

    public function test_manager_can_force_close_open_till_from_admin_and_cannot_reclose_closed_till(): void
    {
        $shift = PosShift::create([
            'admin_id' => $this->cashier->id,
            'status' => 'open',
            'opening_cash' => 150.00,
            'cash_sales' => 90.00,
            'cash_deposits' => 10.00,
            'cash_refunds' => 5.00,
            'cash_withdrawals' => 25.00,
            'terminal_sales' => 120.00,
            'terminal_refunds' => 20.00,
            'expected_cash' => 220.00,
            'orders_count' => 6,
            'opened_at' => now()->subHours(3),
        ]);

        // Manager force-closes open till with counted_cash 218.50 (-1.50 variance) & batch 101.00 (+1.00 variance)
        $this->actingAs($this->manager, 'admin')
            ->postJson("/admin/tills/{$shift->id}/close", [
                'counted_cash' => 218.50,
                'counted_terminal' => 101.00,
                'notes' => 'Cashier left without closing register',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('till.status', 'closed')
            ->assertJsonPath('till.closed_by.name', $this->manager->name)
            ->assertJsonPath('till.counted_cash', 218.5)
            ->assertJsonPath('till.cash_difference', -1.5)
            ->assertJsonPath('till.counted_terminal', 101)
            ->assertJsonPath('till.terminal_difference', 1);

        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertSame($this->manager->id, $shift->closed_by_admin_id);
        $this->assertStringContainsString("[Closed via Admin by {$this->manager->name}]", (string) $shift->notes);

        // Second close attempt must fail cleanly with 422
        $this->actingAs($this->superAdmin, 'admin')
            ->postJson("/admin/tills/{$shift->id}/close", [
                'counted_cash' => 220.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('counted_cash');
    }
}
