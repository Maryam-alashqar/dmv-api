<?php

namespace App\Console\Commands;

use App\Models\UserSubscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark subscriptions whose entitlement period has ended as expired';

    public function handle(): int
    {
        $updated = UserSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$updated} subscription(s).");

        return self::SUCCESS;
    }
}
