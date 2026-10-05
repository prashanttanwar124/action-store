<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:cancel-expired-pending {--minutes=15 : Number of minutes after which pending orders are considered abandoned}')]
#[Description('Cancel abandoned orders in pending_payment status and restock their inventory')]
class CancelExpiredPendingOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cancelledCount = Order::cancelExpiredPendingOrders($minutes);

        if ($cancelledCount > 0) {
            $this->info("Cancelled and restocked {$cancelledCount} expired pending order(s).");
        } else {
            $this->line('No expired pending orders found.');
        }

        return self::SUCCESS;
    }
}
