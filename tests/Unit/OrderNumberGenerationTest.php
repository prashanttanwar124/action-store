<?php

namespace Tests\Unit;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_number_matches_expected_format(): void
    {
        $orderNumber = Order::generateOrderNumber();
        $today = date('Ymd');

        $this->assertMatchesRegularExpression(
            "/^#MM-{$today}-\\d{4}-[A-Z0-9]{4}$/",
            $orderNumber,
            "Order number {$orderNumber} does not match expected format."
        );
    }

    public function test_order_number_increments_sequence_for_the_day(): void
    {
        $today = date('Ymd');

        // Create an existing order with sequence 1001
        Order::factory()->create([
            'order_number' => "#MM-{$today}-1001-A1B2",
        ]);

        $nextOrderNumber = Order::generateOrderNumber();

        $this->assertStringStartsWith("#MM-{$today}-1002-", $nextOrderNumber);
    }

    public function test_order_number_starts_at_1001_when_no_prior_orders_exist(): void
    {
        $today = date('Ymd');
        $orderNumber = Order::generateOrderNumber();

        $this->assertStringStartsWith("#MM-{$today}-1001-", $orderNumber);
    }
}
