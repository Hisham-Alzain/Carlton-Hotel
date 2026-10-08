<?php

namespace App\Console\Commands;

use App\Actions\Loyalty\ForfeitDeletedGuestBalancesAction;
use Illuminate\Console\Command;

/**
 * One-off (not scheduled): run once after deploying the LOY-23 release on an
 * environment that may hold accounts deleted before the deletion forfeit.
 */
class ForfeitDeletedLoyaltyBalances extends Command
{
    protected $signature = 'loyalty:forfeit-deleted';

    protected $description = 'Forfeit loyalty points and close vouchers still held by deleted guest accounts (one-off, safe to re-run)';

    public function handle(ForfeitDeletedGuestBalancesAction $action): int
    {
        $result = $action->handle()['data'];

        $this->info("Forfeited {$result['forfeited_points']} point(s) from {$result['expired_batches']} batch(es) and closed {$result['closed_vouchers']} voucher(s) on {$result['accounts']} deleted account(s).");

        return Command::SUCCESS;
    }
}
