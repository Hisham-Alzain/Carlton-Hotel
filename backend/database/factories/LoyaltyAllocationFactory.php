<?php

namespace Database\Factories;

use App\Models\LoyaltyAllocation;
use App\Models\LoyaltyEarnBatch;
use App\Models\LoyaltyLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAllocation>
 */
class LoyaltyAllocationFactory extends Factory
{
    protected $model = LoyaltyAllocation::class;

    public function definition(): array
    {
        return [
            'ledger_entry_id' => LoyaltyLedgerEntry::factory(),
            'batch_id' => LoyaltyEarnBatch::factory(),
            'points' => 10,
        ];
    }
}
