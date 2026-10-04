<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        OrderItem::truncate();
        Order::truncate();
        Schema::enableForeignKeyConstraints();

        $priya = User::where('email', 'priya@example.com')->first();
        $testUser = User::where('email', 'test@example.com')->first();
        $rahul = User::where('email', 'rahul@example.com')->first();
        $amit = User::where('email', 'amit@example.com')->first();
        $vikram = User::where('email', 'vikram@example.com')->first();
        $atta = Product::where('slug', 'atta')->first();
        $ghee = Product::where('slug', 'ghee')->first();
        $dal = Product::where('slug', 'toor-dal')->first();

        // 1. New Confirmed Order
        $order1 = Order::create([
            'order_number' => '#MM-15508',
            'user_id' => $priya?->id,
            'customer_name' => 'Priya Sharma',
            'customer_email' => 'priya@example.com',
            'customer_phone' => '+1 (555) 345-6789',
            'subtotal' => 28.98,
            'discount' => 0.00,
            'total' => 28.98,
            'points_earned' => 28,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Today 4–5 pm',
            'pickup_location' => 'Masala Mart — Edison',
            'status' => 'confirmed',
            'notes' => 'Please pack fresh batch of flour.',
            'created_at' => now()->subMinutes(12),
        ]);

        if ($atta) {
            OrderItem::create([
                'order_id' => $order1->id,
                'product_id' => $atta->id,
                'name' => $atta->name,
                'size' => $atta->size_main,
                'unit_price' => $atta->price,
                'quantity' => 1,
                'total_price' => $atta->price,
                'is_subscribed' => false,
                'image' => $atta->image,
            ]);
        }
        if ($ghee) {
            OrderItem::create([
                'order_id' => $order1->id,
                'product_id' => $ghee->id,
                'name' => $ghee->name,
                'size' => $ghee->size_main,
                'unit_price' => $ghee->price,
                'quantity' => 1,
                'total_price' => $ghee->price,
                'is_subscribed' => false,
                'image' => $ghee->image,
            ]);
        }

        // 2. Packing Order
        $order2 = Order::create([
            'order_number' => '#MM-15509',
            'user_id' => $testUser?->id,
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+1 (555) 789-0123',
            'subtotal' => 18.99,
            'discount' => 0.00,
            'total' => 18.99,
            'points_earned' => 18,
            'payment_method' => 'apple-pay',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Today 5–6 pm',
            'pickup_location' => 'Masala Mart — Edison',
            'status' => 'packing',
            'notes' => null,
            'created_at' => now()->subMinutes(35),
        ]);

        if ($atta) {
            OrderItem::create([
                'order_id' => $order2->id,
                'product_id' => $atta->id,
                'name' => $atta->name,
                'size' => $atta->size_main,
                'unit_price' => $atta->price,
                'quantity' => 1,
                'total_price' => $atta->price,
                'is_subscribed' => false,
                'image' => $atta->image,
            ]);
        }

        // 3. Ready for pickup
        $order3 = Order::create([
            'order_number' => '#MM-15510',
            'user_id' => $rahul?->id,
            'customer_name' => 'Rahul Khanna',
            'customer_email' => 'rahul@example.com',
            'customer_phone' => '+1 (555) 890-4321',
            'subtotal' => 19.98,
            'discount' => 0.00,
            'total' => 19.98,
            'points_earned' => 19,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Ready in 1 hr',
            'pickup_location' => 'Masala Mart — Edison',
            'status' => 'ready_for_pickup',
            'notes' => 'Curbside Bay 2',
            'created_at' => now()->subHours(1),
        ]);

        if ($ghee) {
            OrderItem::create([
                'order_id' => $order3->id,
                'product_id' => $ghee->id,
                'name' => $ghee->name,
                'size' => $ghee->size_main,
                'unit_price' => $ghee->price,
                'quantity' => 2,
                'total_price' => $ghee->price * 2,
                'is_subscribed' => false,
                'image' => $ghee->image,
            ]);
        }

        // 4. Completed Order
        $order4 = Order::create([
            'order_number' => '#MM-15505',
            'user_id' => $amit?->id,
            'customer_name' => 'Amit Patel',
            'customer_email' => 'amit@example.com',
            'customer_phone' => '+1 (555) 234-5678',
            'subtotal' => 15.98,
            'discount' => 0.00,
            'total' => 15.98,
            'points_earned' => 15,
            'payment_method' => 'google-pay',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => 'Yesterday 3–4 pm',
            'pickup_location' => 'Masala Mart — Edison',
            'status' => 'completed',
            'notes' => null,
            'created_at' => now()->subDay(),
        ]);

        if ($dal) {
            OrderItem::create([
                'order_id' => $order4->id,
                'product_id' => $dal->id,
                'name' => $dal->name,
                'size' => $dal->size_main,
                'unit_price' => $dal->price,
                'quantity' => 2,
                'total_price' => $dal->price * 2,
                'is_subscribed' => false,
                'image' => $dal->image,
            ]);
        }

        // 5. Completed Order for Vikram Malhotra (repeat customer)
        $order5 = Order::create([
            'order_number' => '#MM-15499',
            'user_id' => $vikram?->id,
            'customer_name' => 'Vikram Malhotra',
            'customer_email' => 'vikram@example.com',
            'customer_phone' => '+1 (555) 678-9012',
            'subtotal' => 36.97,
            'discount' => 0.00,
            'total' => 36.97,
            'points_earned' => 36,
            'payment_method' => 'card',
            'fulfillment_type' => 'Store Pickup',
            'pickup_slot' => '3 days ago',
            'pickup_location' => 'Masala Mart — Edison',
            'status' => 'completed',
            'notes' => null,
            'created_at' => now()->subDays(3),
        ]);

        if ($atta) {
            OrderItem::create([
                'order_id' => $order5->id,
                'product_id' => $atta->id,
                'name' => $atta->name,
                'size' => $atta->size_main,
                'unit_price' => $atta->price,
                'quantity' => 1,
                'total_price' => $atta->price,
                'is_subscribed' => false,
                'image' => $atta->image,
            ]);
        }
        if ($ghee) {
            OrderItem::create([
                'order_id' => $order5->id,
                'product_id' => $ghee->id,
                'name' => $ghee->name,
                'size' => $ghee->size_main,
                'unit_price' => $ghee->price,
                'quantity' => 1,
                'total_price' => $ghee->price,
                'is_subscribed' => false,
                'image' => $ghee->image,
            ]);
        }
        if ($dal) {
            OrderItem::create([
                'order_id' => $order5->id,
                'product_id' => $dal->id,
                'name' => $dal->name,
                'size' => $dal->size_main,
                'unit_price' => $dal->price,
                'quantity' => 1,
                'total_price' => $dal->price,
                'is_subscribed' => false,
                'image' => $dal->image,
            ]);
        }
    }
}
