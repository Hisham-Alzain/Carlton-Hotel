<?php

namespace App\Exceptions;

/** Phase 10 (Q16): the reward is inactive, deleted or out of stock. */
class LoyaltyRewardUnavailableException extends DomainException
{
    public function errorCode(): string { return 'loyalty_reward_unavailable'; }
    public function statusCode(): int   { return 422; }
}
