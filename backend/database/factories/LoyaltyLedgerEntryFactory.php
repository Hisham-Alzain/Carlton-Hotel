<?php

namespace Database\Factories;

use App\Enums\LoyaltyEntryType;
use App\Models\Guest;
use App\Models\LoyaltyLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyLedgerEntry>
 */
class LoyaltyLedgerEntryFactory extends Factory
{
    protected $model = LoyaltyLedgerEntry::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'type' => LoyaltyEntryType::ADJUST,
            'source' => null,
            'points' => 500,
            'batch_id' => null,
            'voucher_id' => null,
            'reservation_id' => null,
            'folio_id' => null,
            'reverses_entry_id' => null,
            'performed_by' => null,
            'reason' => null,
            'shortfall_points' => 0,
            'discount_usd' => null,
            'idempotency_key' => 'factory:'.Str::uuid(),
            'occurred_at' => now(),
        ];
    }
}
