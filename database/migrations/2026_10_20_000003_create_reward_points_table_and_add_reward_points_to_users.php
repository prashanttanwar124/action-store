<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('reward_points')->default(100)->after('password');
        });

        Schema::create('reward_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('description');
            $table->timestamps();

            $table->index(['user_id', 'order_id']);
        });

        // Seed existing users with welcome bonus and existing non-cancelled order points if table has rows
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            DB::table('reward_points')->insert([
                'user_id' => $user->id,
                'order_id' => null,
                'points' => 100,
                'description' => 'Welcome bonus',
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ]);

            $orders = DB::table('orders')
                ->where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->get();

            $orderPointsSum = 0;
            foreach ($orders as $order) {
                $refunded = (float) ($order->refunded_total ?? 0);
                $net = max(0, (float) $order->total - $refunded);
                $points = (int) floor($net);
                if ($points > 0) {
                    DB::table('reward_points')->insert([
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'points' => $points,
                        'description' => "Points earned for order {$order->order_number}",
                        'created_at' => $order->created_at ?? now(),
                        'updated_at' => $order->updated_at ?? now(),
                    ]);
                    $orderPointsSum += $points;
                }
            }

            DB::table('users')->where('id', $user->id)->update([
                'reward_points' => 100 + $orderPointsSum,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_points');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reward_points');
        });
    }
};
