<?php

namespace App\Console\Commands;

use App\Actions\Loyalty\ExpireLoyaltyBatchesAction;
use Illuminate\Console\Command;

class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire loyalty point batches and vouchers past their expiry';

    public function handle(ExpireLoyaltyBatchesAction $action): int
    {
        $result = $action->handle()['data'];

        $this->info("Expired {$result['batches_expired']} loyalty batch(es) and {$result['vouchers_expired']} voucher(s).");

        return Command::SUCCESS;
    }
}
