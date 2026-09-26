<?php

namespace App\Console\Commands;

use App\Actions\Booking\RevokeExpiredDigitalKeysAction;
use Illuminate\Console\Command;

class ExpireDigitalKeys extends Command
{
    protected $signature   = 'stays:expire-digital-keys';
    protected $description = 'Revoke digital keys whose expiry has passed';

    public function handle(RevokeExpiredDigitalKeysAction $action): int
    {
        $revoked = $action->handle();
        $this->info("Revoked {$revoked} expired digital key(s).");
        return Command::SUCCESS;
    }
}
