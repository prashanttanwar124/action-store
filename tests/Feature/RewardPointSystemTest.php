<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RewardPoint;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RewardPointSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_creates_welcome_bonus_ledger_entry_and_sets_balance(): void
    {
        $response = $this->post('/register', [
            'name' => 'Meera Patel',
            'email' => 'meera@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'meera@example.com')->firstOrFail();

        // Verify balance column on users table
        $this->assertSame(100, (int) $user->reward_points);

        // Verify reward_points ledger entry with primary key id, user_id, null order_id
        $ledgerEntry = RewardPoint::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($ledgerEntry->id);
        $this->assertSame($user->id, (int) $ledgerEntry->user_id);
        $this->assertNull($ledgerEntry->order_id);
        $this->assertSame(100, (int) $ledgerEntry->points);
        $this->assertSame('Welcome bonus', $ledgerEntry->description);

        // Verify shared via Inertia /account
        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.reward_points', 100)
            );
    }

    public function test_user_factory_creation_creates_welcome_bonus_record(): void
    {
        $user = User::factory()->create();

        $this->assertSame(100, (int) $user->reward_points);
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $user->id,
            'order_id' => null,
            'points' => 100,
            'description' => 'Welcome bonus',
        ]);
    }

    public function test_placing_order_records_ledger_entry_and_updates_user_balance(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 54.80,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
        ]);

        // User earned 54 points (1 pt per $1 net spent)
        $this->assertSame(154, (int) $user->fresh()->reward_points);

        $orderLedger = RewardPoint::where('order_id', $order->id)->firstOrFail();
        $this->assertNotNull($orderLedger->id);
        $this->assertSame($user->id, (int) $orderLedger->user_id);
        $this->assertSame($order->id, (int) $orderLedger->order_id);
        $this->assertSame(54, (int) $orderLedger->points);
        $this->assertStringContainsString((string) $order->order_number, $orderLedger->description);

        // Verify Inertia shares database reward_points
        $this->actingAs($user->refresh())->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('auth.user.reward_points', 154)
            );
    }

    public function test_order_cancellation_adjusts_ledger_and_reverts_user_balance(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 75.00,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(175, (int) $user->fresh()->reward_points);

        // Cancel the order
        $order->cancelAndRestock('Customer requested cancellation');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Verify adjustment ledger entry exists
        $adjustments = RewardPoint::where('order_id', $order->id)->get();
        $this->assertCount(2, $adjustments);

        $earnEntry = $adjustments->firstWhere('points', 75);
        $reversalEntry = $adjustments->firstWhere('points', -75);

        $this->assertNotNull($earnEntry);
        $this->assertNotNull($reversalEntry);
        $this->assertSame(-75, (int) $reversalEntry->points);
        $this->assertStringContainsString('Cancelled', $reversalEntry->description);
    }

    public function test_order_partial_refund_adjusts_ledger_and_updates_user_balance(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 80.00,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(180, (int) $user->fresh()->reward_points);

        // Refund $25 from the order
        $order->update(['refunded_total' => 25.00]);

        // Net spent is 80 - 25 = 55 -> points should be 100 + 55 = 155
        $this->assertSame(155, (int) $user->fresh()->reward_points);

        $adjustments = RewardPoint::where('order_id', $order->id)->get();
        $this->assertCount(2, $adjustments);

        $reversalEntry = $adjustments->firstWhere('points', -25);
        $this->assertNotNull($reversalEntry);
        $this->assertSame(-25, (int) $reversalEntry->points);
        $this->assertStringContainsString('Refund', $reversalEntry->description);
    }

    public function test_guest_orders_do_not_create_reward_points_ledger(): void
    {
        $initialCount = RewardPoint::count();

        Order::factory()->create([
            'user_id' => null,
            'total' => 120.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame($initialCount, RewardPoint::count());
    }

    public function test_dynamic_storesetting_branding_derives_brand_name_and_shares_via_inertia(): void
    {
        $setting = StoreSetting::current();

        // 1. Default format: "Masala Mart — Main St."
        $setting->update(['name' => 'Masala Mart — Main St.']);
        $this->assertSame('Masala Mart', $setting->fresh()->brand_name);

        // 2. Dash separator: "Spice Bazaar - Downtown"
        $setting->update(['name' => 'Spice Bazaar - Downtown']);
        $this->assertSame('Spice Bazaar', $setting->fresh()->brand_name);

        // 3. Simple brand with no location suffix: "Royal Mart"
        $setting->update(['name' => 'Royal Mart']);
        $this->assertSame('Royal Mart', $setting->fresh()->brand_name);

        // 4. Verify Inertia share includes brand_name in storeInfo
        $response = $this->get('/account');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('storeInfo.name', 'Royal Mart')
            ->where('storeInfo.brand_name', 'Royal Mart')
        );
    }

    public function test_database_seeder_accurately_seeds_reward_points_and_user_balances(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::all();
        $this->assertGreaterThan(0, $users->count());

        foreach ($users as $user) {
            // Every user must have a welcome bonus ledger entry
            $welcomeBonus = RewardPoint::where('user_id', $user->id)
                ->whereNull('order_id')
                ->where('points', 100)
                ->first();

            $this->assertNotNull($welcomeBonus, "User {$user->email} missing welcome bonus ledger record");

            // Every user's reward_points balance column must equal the sum of their ledger entries
            $ledgerSum = (int) RewardPoint::where('user_id', $user->id)->sum('points');
            $this->assertSame($ledgerSum, (int) $user->reward_points, "User {$user->email} balance does not match ledger sum");
        }

        // Test specific seeded users with orders
        $priya = User::where('email', 'priya@example.com')->firstOrFail();
        $this->assertSame(128, (int) $priya->reward_points); // 100 welcome + 28 order
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $priya->id,
            'points' => 28,
        ]);

        $vikram = User::where('email', 'vikram@example.com')->firstOrFail();
        $this->assertSame(136, (int) $vikram->reward_points); // 100 welcome + 36 order
        $this->assertDatabaseHas('reward_points', [
            'user_id' => $vikram->id,
            'points' => 36,
        ]);

        $ananya = User::where('email', 'ananya@example.com')->firstOrFail();
        $this->assertSame(100, (int) $ananya->reward_points); // 100 welcome + 0 order
    }

    public function test_user_create_has_in_memory_default_balance_and_ledger_entry(): void
    {
        $user = User::create([
            'name' => 'Direct Creation User',
            'email' => 'direct@example.com',
            'password' => 'DirectSecret123!',
        ]);

        // In-memory model must immediately reflect default balance without requiring fresh()
        $this->assertSame(100, $user->reward_points);

        // Database row must have 100
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'reward_points' => 100,
        ]);

        // Ledger must contain exactly 1 entry: the welcome bonus
        $entries = RewardPoint::where('user_id', $user->id)->get();
        $this->assertCount(1, $entries);
        $this->assertSame(100, $entries[0]->points);
        $this->assertNull($entries[0]->order_id);
        $this->assertSame('Welcome bonus', $entries[0]->description);
    }

    public function test_order_deletion_removes_ledger_entries_and_recalculates_user_balance(): void
    {
        $user = User::factory()->create();
        $this->assertSame(100, (int) $user->reward_points);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 65.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(165, (int) $user->fresh()->reward_points);
        $this->assertSame(2, $user->rewardPoints()->count());

        // Delete order: should remove its ledger entries and reset user balance
        $order->delete();

        $this->assertSame(100, (int) $user->fresh()->reward_points);
        $this->assertSame(1, $user->rewardPoints()->count());
        $this->assertSame(0, (int) RewardPoint::where('order_id', $order->id)->count());
    }

    public function test_order_reassignment_transfers_points_between_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $userA->id,
            'total' => 45.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(145, (int) $userA->fresh()->reward_points);
        $this->assertSame(100, (int) $userB->fresh()->reward_points);

        // Reassign order to user B
        $order->update(['user_id' => $userB->id]);

        $this->assertSame(100, (int) $userA->fresh()->reward_points);
        $this->assertSame(145, (int) $userB->fresh()->reward_points);

        // Reassign to null (guest)
        $order->update(['user_id' => null]);

        $this->assertSame(100, (int) $userA->fresh()->reward_points);
        $this->assertSame(100, (int) $userB->fresh()->reward_points);
        $this->assertSame(0, (int) RewardPoint::where('order_id', $order->id)->count());
    }

    public function test_multiple_partial_refunds_and_subsequent_cancellation(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 100.00,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(200, (int) $user->fresh()->reward_points);

        // Refund 1: $20
        $order->update(['refunded_total' => 20.00]);
        $this->assertSame(180, (int) $user->fresh()->reward_points);

        // Refund 2: another $35 ($55 total)
        $order->update(['refunded_total' => 55.00]);
        $this->assertSame(145, (int) $user->fresh()->reward_points);

        // Cancellation: remaining $45 reverted
        $order->cancelAndRestock('Final cancellation');
        $this->assertSame(100, (int) $user->fresh()->reward_points);

        // Net points earned for this order must be 0
        $netOrderPoints = (int) RewardPoint::where('order_id', $order->id)->sum('points');
        $this->assertSame(0, $netOrderPoints);
    }

    public function test_over_refund_never_reduces_order_points_below_zero(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 50.00,
            'refunded_total' => 0.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(150, (int) $user->fresh()->reward_points);

        // Refund exceeds order total
        $order->update(['refunded_total' => 75.00]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);
        $netOrderPoints = (int) RewardPoint::where('order_id', $order->id)->sum('points');
        $this->assertSame(0, $netOrderPoints);
    }

    public function test_brand_name_derivation_handles_all_unicode_dash_and_hyphen_variants(): void
    {
        $setting = StoreSetting::current();

        $cases = [
            'Masala Mart — Main St.' => 'Masala Mart',
            'Masala Mart—Main St.' => 'Masala Mart',
            'Masala Mart – Downtown' => 'Masala Mart',
            'Masala Mart–Downtown' => 'Masala Mart',
            'Spice Bazaar - Uptown' => 'Spice Bazaar',
            'Click-and-Collect Groceries' => 'Click-and-Collect Groceries',
            'Spice-Bazaar - Midtown' => 'Spice-Bazaar',
            'Royal Mart' => 'Royal Mart',
            '   ' => 'Store',
        ];

        foreach ($cases as $storeName => $expectedBrand) {
            $setting->update(['name' => $storeName]);
            $this->assertSame(
                $expectedBrand,
                $setting->fresh()->brand_name,
                "Failed to derive '{$expectedBrand}' from '{$storeName}'"
            );
        }
    }

    public function test_order_reassignment_via_direct_database_query_reconciles_both_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $userA->id,
            'total' => 60.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(160, (int) $userA->fresh()->reward_points);
        $this->assertSame(100, (int) $userB->fresh()->reward_points);

        // Reassign in database without in-memory event hooks
        DB::table('orders')->where('id', $order->id)->update(['user_id' => $userB->id]);

        // Load fresh instance and sync
        $freshOrder = Order::find($order->id);
        $freshOrder->syncRewardPoints();

        $this->assertSame(100, (int) $userA->fresh()->reward_points, 'User A balance must revert to 100 after order moved');
        $this->assertSame(160, (int) $userB->fresh()->reward_points, 'User B balance must receive 60 points for the order');

        $this->assertDatabaseMissing('reward_points', [
            'order_id' => $order->id,
            'user_id' => $userA->id,
        ]);
        $this->assertDatabaseHas('reward_points', [
            'order_id' => $order->id,
            'user_id' => $userB->id,
            'points' => 60,
        ]);
    }

    public function test_order_converted_to_guest_via_direct_database_query_purges_points(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 48.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(148, (int) $user->fresh()->reward_points);

        // Reassign to null (guest) in database
        DB::table('orders')->where('id', $order->id)->update(['user_id' => null]);

        $freshOrder = Order::find($order->id);
        $freshOrder->syncRewardPoints();

        $this->assertSame(100, (int) $user->fresh()->reward_points, 'User balance must revert to 100 when order converted to guest');
        $this->assertSame(0, RewardPoint::where('order_id', $order->id)->count(), 'Orphaned reward points for guest order must be deleted');
    }

    public function test_zero_and_negative_dollar_orders_do_not_produce_points(): void
    {
        $user = User::factory()->create();
        $this->assertSame(100, (int) $user->reward_points);

        $zeroOrder = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 0.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);
        $this->assertSame(0, RewardPoint::where('order_id', $zeroOrder->id)->count());

        $negativeOrder = Order::factory()->create([
            'user_id' => $user->id,
            'total' => -15.00,
            'status' => 'confirmed',
        ]);

        $this->assertSame(100, (int) $user->fresh()->reward_points);
        $this->assertSame(0, RewardPoint::where('order_id', $negativeOrder->id)->count());
    }

    public function test_unsaved_order_sync_reward_points_does_not_wipe_out_welcome_bonuses(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $initialWelcomeCount = RewardPoint::whereNull('order_id')->count();
        $this->assertGreaterThanOrEqual(2, $initialWelcomeCount);

        // An unsaved order without an ID or user_id
        $unsavedOrder = new Order([
            'status' => 'confirmed',
            'total' => 45.00,
        ]);

        $unsavedOrder->syncRewardPoints();

        // Welcome bonuses for existing users must remain strictly intact
        $this->assertSame($initialWelcomeCount, RewardPoint::whereNull('order_id')->count());
        $this->assertSame(100, (int) $user1->fresh()->reward_points);
        $this->assertSame(100, (int) $user2->fresh()->reward_points);
    }

    public function test_unsaved_order_with_user_sync_does_not_pollute_welcome_bonus(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $initialWelcomeCount = RewardPoint::whereNull('order_id')->count();

        // An unsaved order with user_id attached
        $unsavedOrder = new Order([
            'user_id' => $user1->id,
            'status' => 'confirmed',
            'total' => 60.00,
        ]);

        $unsavedOrder->syncRewardPoints();

        // User 2's bonus must not be deleted, and User 1's bonus must not be polluted with negative adjustments
        $this->assertSame($initialWelcomeCount, RewardPoint::whereNull('order_id')->count());
        $this->assertSame(100, (int) $user1->fresh()->reward_points);
        $this->assertSame(100, (int) $user2->fresh()->reward_points);
    }

    public function test_unsaved_user_balance_refresh_does_not_crash_with_query_exception(): void
    {
        $unsavedUser = new User;

        $this->assertSame(100, $unsavedUser->refreshRewardPointsBalance());
    }

    public function test_user_created_with_custom_points_creates_consistent_ledger_entry_and_persists_balance(): void
    {
        $user = User::factory()->create(['reward_points' => 250]);

        // Model attribute and database row must reflect 250
        $this->assertSame(250, (int) $user->reward_points);
        $this->assertSame(250, (int) $user->fresh()->reward_points);

        // Ledger sum must strictly equal 250
        $ledgerSum = (int) $user->rewardPoints()->sum('points');
        $this->assertSame(250, $ledgerSum);

        // Refreshing balance must remain 250
        $refreshed = $user->refreshRewardPointsBalance();
        $this->assertSame(250, $refreshed);
        $this->assertSame(250, (int) $user->fresh()->reward_points);
    }
}
