<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CheckoutValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(14, 0));

        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_catalog_price_overrides_tampered_price_and_name(): void
    {
        $product = Product::factory()->create(['price' => 20]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => 'Fake name', 'price' => 0.01, 'quantity' => 2]],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['subtotal' => 40, 'total' => 40, 'points_earned' => 40]);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'name' => $product->name, 'unit_price' => 20]);
    }

    public function test_unknown_item_cannot_be_resolved_by_a_forged_name(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/checkout', [
            'items' => [['id' => 'missing-product', 'name' => $product->name, 'price' => 1, 'quantity' => 1]],
        ])->assertUnprocessable()->assertInvalid(['items.0.id' => 'This item is no longer available.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 50]);
    }

    public function test_past_custom_pickup_is_rejected_without_changing_stock(): void
    {
        $this->travelTo(now()->setTime(17, 0));
        $product = Product::factory()->create();

        $this->postJson('/checkout', $this->scheduledPayload($product, ['pickup_time' => '12:00']))
            ->assertUnprocessable()->assertInvalid(['pickup_time' => 'Choose a future pickup time']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 50]);
    }

    public function test_pickup_requires_preparation_time(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $product = Product::factory()->create();

        $this->postJson('/checkout', $this->scheduledPayload($product, ['pickup_time' => '10:05']))
            ->assertUnprocessable()->assertInvalid(['pickup_time' => 'allowing time for preparation']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pickup_outside_store_hours_is_rejected(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $product = Product::factory()->create();

        $this->postJson('/checkout', $this->scheduledPayload($product, ['pickup_time' => '22:00']))
            ->assertUnprocessable()->assertInvalid(['pickup_time' => 'within store hours']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_pickup_window_is_rejected(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        StoreSetting::current()->update(['pickup_slots' => [
            ['id' => '17-18', 'label' => '5:00 PM – 6:00 PM', 'startHour' => 17, 'active' => false],
        ]]);
        $product = Product::factory()->create();

        $this->postJson('/checkout', $this->scheduledPayload($product, [
            'pickup_timing_type' => 'slot', 'pickup_slot' => '5:00 PM – 6:00 PM',
        ]))->assertUnprocessable()->assertInvalid(['pickup_slot' => 'Choose an available pickup window.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_half_hour_window_in_current_hour_can_be_booked(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        StoreSetting::current()->update(['pickup_slot_duration_minutes' => 30]);
        $product = Product::factory()->create();

        $this->postJson('/checkout', $this->scheduledPayload($product, [
            'pickup_timing_type' => 'slot', 'pickup_slot' => '10:30 AM – 11:00 AM',
        ]))->assertCreated();

        $this->assertDatabaseHas('orders', ['pickup_slot' => now()->toDateString().' · 10:30 AM – 11:00 AM']);
    }

    public function test_tomorrow_custom_pickup_is_saved_with_actual_date(): void
    {
        $this->travelTo(now()->setTime(17, 0));
        $product = Product::factory()->create();
        $date = now()->addDay()->toDateString();

        $this->postJson('/checkout', $this->scheduledPayload($product, [
            'pickup_date' => $date, 'pickup_time' => '09:00',
        ]))->assertCreated();

        $this->assertDatabaseHas('orders', ['pickup_slot' => $date.' · 9:00 AM (Custom Time)']);
    }

    public function test_paused_pickup_is_rejected(): void
    {
        StoreSetting::current()->update(['is_pickup_active' => false]);
        $product = Product::factory()->create();

        $this->postJson('/checkout', ['items' => [
            ['id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1],
        ]])->assertUnprocessable()->assertInvalid(['pickup_slot' => 'Store pickup is currently paused.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_recipe_kit_slug_uses_catalog_price_and_decrements_ingredients(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $kit = RecipeKit::create(['name' => 'Paneer Kit', 'slug' => 'paneer-kit', 'price' => 25, 'is_active' => true]);
        $kit->products()->attach($product, ['quantity' => 2]);

        $this->postJson('/checkout', ['items' => [
            ['id' => $kit->slug, 'name' => 'Fake Kit', 'price' => 0, 'quantity' => 2],
        ]])->assertCreated();

        $this->assertDatabaseHas('orders', ['total' => 50]);
        $this->assertDatabaseHas('order_items', ['name' => 'Paneer Kit', 'unit_price' => 25]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 6]);
    }

    public function test_delivery_fee_and_address_use_validated_order_data(): void
    {
        StoreSetting::current()->update([
            'is_delivery_active' => true,
            'delivery_days' => [strtolower(now()->format('l'))],
        ]);

        $product = Product::factory()->create(['price' => 20]);

        $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 100, 'quantity' => 1]],
            'fulfillment_type' => 'Home Delivery',
            'delivery_address' => '456 Main St, Apt 2B',
        ])->assertCreated();

        $this->assertDatabaseHas('orders', [
            'subtotal' => 20, 'total' => 24.99, 'delivery_fee' => 4.99,
            'delivery_address' => '456 Main St, Apt 2B',
        ]);
    }

    public function test_asap_pickup_is_rejected_outside_store_hours(): void
    {
        // 11:30 PM (after store close)
        $this->travelTo(now()->setTime(23, 30));
        $product = Product::factory()->create(['price' => 10]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_timing_mode']);
    }

    public function test_expected_total_mismatch_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 25]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 25, 'quantity' => 1]],
            'expected_total' => 20.00, // Client expected 20 but server calculates 25
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total']);
    }

    public function test_date_beyond_tomorrow_is_rejected(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $product = Product::factory()->create();

        $this->postJson('/checkout', $this->scheduledPayload($product, [
            'pickup_date' => now()->addDays(2)->toDateString(),
        ]))->assertUnprocessable()->assertInvalid(['pickup_date' => 'Choose today or tomorrow for pickup.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_minimum_order_amount_is_enforced(): void
    {
        StoreSetting::current()->update(['min_order_amount' => 25.00]);
        $product = Product::factory()->create(['price' => 10.00]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
        ]);

        $response->assertUnprocessable();
        $response->assertInvalid(['total']);
        $this->assertEquals('min_order_not_met', $response->json('errors.error_code.0'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_store_pickup_operating_days_are_enforced(): void
    {
        // 2026-10-04 is a Sunday
        $this->travelTo(Carbon::parse('2026-10-04 10:00', config('app.timezone')));
        StoreSetting::current()->update([
            'pickup_days' => ['monday', 'tuesday'], // Sunday closed
        ]);

        $product = Product::factory()->create(['price' => 15.00]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'asap',
        ]);

        $response->assertUnprocessable();
        $response->assertInvalid(['pickup_slot']);
        $this->assertEquals('store_closed', $response->json('errors.error_code.0'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_slot_capacity_limit_is_enforced_and_rejects_overbooking(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $store = StoreSetting::current();
        $store->update([
            'max_orders_per_slot' => 1,
            'prep_time_minutes' => 15,
            'pickup_slot_start_time' => '09:00',
            'pickup_slot_end_time' => '21:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);

        $product = Product::factory()->create(['price' => 15.00]);
        $slotLabel = '10:00 AM – 11:00 AM';
        $today = now()->toDateString();

        // 1st order takes the only available capacity
        $response1 = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'slot',
            'pickup_date' => $today,
            'pickup_slot' => $slotLabel,
        ])->assertCreated();

        $this->assertDatabaseCount('orders', 1);

        // 2nd order to the exact same slot is rejected with slot_full
        $response2 = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'slot',
            'pickup_date' => $today,
            'pickup_slot' => $slotLabel,
        ]);

        $response2->assertUnprocessable();
        $response2->assertInvalid(['pickup_slot']);
        $this->assertEquals('slot_full', $response2->json('errors.error_code.0'));
        $this->assertDatabaseCount('orders', 1); // No 2nd order created!
    }

    public function test_omitting_pickup_timing_mode_with_custom_pickup_slot_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 15.00]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 15.00, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Bypassed Slot Label',
        ]);

        $response->assertUnprocessable();
        $response->assertInvalid(['pickup_timing_mode']);
        $this->assertEquals('timing_mode_required', $response->json('errors.error_code.0'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_omitting_pickup_timing_mode_outside_store_hours_is_rejected(): void
    {
        // 11:30 PM (after store close)
        $this->travelTo(now()->setTime(23, 30));
        $product = Product::factory()->create(['price' => 10]);

        $response = $this->postJson('/checkout', [
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 10, 'quantity' => 1]],
            'fulfillment_type' => 'Store Pickup',
            // Omitting pickup_timing_mode defaults to asap and must check store hours
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_timing_mode']);
        $this->assertEquals('slot_expired', $response->json('errors.error_code.0'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function scheduledPayload(Product $product, array $overrides = []): array
    {
        return array_replace([
            'items' => [['id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'quantity' => 1]],
            'pickup_timing_mode' => 'scheduled',
            'pickup_timing_type' => 'custom',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '12:00',
        ], $overrides);
    }
}
