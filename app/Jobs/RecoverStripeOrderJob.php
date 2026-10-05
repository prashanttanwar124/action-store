<?php

namespace App\Jobs;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecoverStripeOrderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $intentData
     */
    public function __construct(public array $intentData) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $stripePaymentId = $this->intentData['id'] ?? null;
        if (empty($stripePaymentId)) {
            return;
        }

        if (Order::where('stripe_payment_id', $stripePaymentId)->exists()) {
            Log::info("Delayed recovery: Order already completed by customer checkout for payment {$stripePaymentId}. Skipping.");

            return;
        }

        Log::info("Delayed recovery: Order not completed by customer within grace window for payment {$stripePaymentId}. Recovering now.");
        StripeWebhookController::processRecovery((object) $this->intentData);
    }
}
