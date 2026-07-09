<?php

namespace App\Console\Commands;

use App\Actions\Booking\ReleaseExpiredHoldsAction;
use Illuminate\Console\Command;

class ReleaseExpiredHolds extends Command
{
    protected $signature   = 'booking:release-holds';
    protected $description = 'Cancel pending_verification reservations whose hold has expired';

    public function handle(ReleaseExpiredHoldsAction $action): int
    {
        $released = $action->handle();
        $this->info("Released {$released} expired hold(s).");
        return Command::SUCCESS;
    }
}
