<?php

namespace App\Console\Commands;

use App\Services\StripePayments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('checkouts:release-expired')]
#[Description('Release unpaid checkouts whose customer has been inactive too long: cancel the Stripe payment and delete them')]
class ReleaseExpiredCheckouts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StripePayments $payments): int
    {
        $releasedCount = $payments->releaseExpiredCheckouts();

        if ($releasedCount > 0) {
            $this->info("Released {$releasedCount} expired checkout(s).");
        } else {
            $this->line('No expired checkouts found.');
        }

        return self::SUCCESS;
    }
}
