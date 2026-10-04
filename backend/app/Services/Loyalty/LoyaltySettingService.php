<?php

namespace App\Services\Loyalty;

use App\Actions\Loyalty\UpdateLoyaltySettingsAction;
use App\Models\User;
use App\Support\LoyaltyProgram;

/**
 * Staff view of the loyalty program settings singleton (Phase 10, Q4).
 * A plain service: the singleton has no list, filter or route-bound model, so
 * BaseService's CRUD verbs do not apply.
 */
class LoyaltySettingService
{
    public function __construct(private readonly UpdateLoyaltySettingsAction $update) {}

    /** @return array{data: LoyaltyProgram, code: int} reads fresh and never writes */
    public function show(): array
    {
        return ['data' => LoyaltyProgram::current(), 'code' => 200];
    }

    /**
     * @param  array<string, mixed>  $values  only the keys the caller sent
     * @return array{data: LoyaltyProgram, code: int}
     */
    public function update(array $values, User $actor): array
    {
        return $this->update->handle($values, $actor);
    }
}
