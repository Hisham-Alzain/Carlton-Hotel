<?php

namespace Database\Factories;

use App\Enums\LoyaltyBatchSource;
use App\Enums\LoyaltyBatchStatus;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\LoyaltyEarnBatch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyEarnBatch>
 */
class LoyaltyEarnBatchFactory extends Factory
{
    protected $model = LoyaltyEarnBatch::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'source' => LoyaltyBatchSource::MANUAL,
            'folio_id' => null,
            'awarded_by' => null,
            'reason' => null,
            'points' => 500,
            'points_remaining' => 500,
            'earned_at' => now(),
            'expires_at' => now()->addMonths(24),
            'expiry_warned_at' => null,
            'status' => LoyaltyBatchStatus::ACTIVE,
        ];
    }

    /** Past its expiry and already swept: nothing left to spend. */
    public function expired(): static
    {
        return $this->state([
            'status' => LoyaltyBatchStatus::EXPIRED,
            'points_remaining' => 0,
            'earned_at' => now()->subMonths(25),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function depleted(): static
    {
        return $this->state([
            'status' => LoyaltyBatchStatus::DEPLETED,
            'points_remaining' => 0,
        ]);
    }

    public function expiringAt(CarbonInterface $instant): static
    {
        return $this->state(['expires_at' => $instant]);
    }

    /** The once-per-settled-folio earn batch for one source bucket. */
    public function forFolio(Folio $folio, LoyaltyBatchSource $source): static
    {
        return $this->state([
            'folio_id' => $folio->id,
            'source' => $source,
        ]);
    }
}
