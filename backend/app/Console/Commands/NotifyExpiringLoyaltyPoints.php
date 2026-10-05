<?php

namespace App\Console\Commands;

use App\Actions\Loyalty\NotifyExpiringLoyaltyPointsAction;
use Illuminate\Console\Command;

class NotifyExpiringLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:notify-expiring';

    protected $description = 'Warn guests whose loyalty points expire soon';

    public function handle(NotifyExpiringLoyaltyPointsAction $action): int
    {
        $result = $action->handle()['data'];

        $this->info("Warned {$result['guests_notified']} guest(s) about expiring loyalty points ({$result['failures']} failure(s)).");

        return $result['failures'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
