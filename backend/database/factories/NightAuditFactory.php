<?php

namespace Database\Factories;

use App\Enums\NightAuditStatus;
use App\Models\NightAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NightAudit>
 */
class NightAuditFactory extends Factory
{
    protected $model = NightAudit::class;

    public function definition(): array
    {
        return [
            'business_date'  => $this->faker->unique()->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
            'status'         => NightAuditStatus::OPEN,
            'snapshot_basis' => NightAudit::SNAPSHOT_BASIS,
            'evaluated_at'   => now(),
            'opened_by'      => null,
            'closed_by'      => null,
            'closed_at'      => null,
        ];
    }

    public function closed(?User $by = null): static
    {
        return $this->state(fn () => [
            'status'    => NightAuditStatus::CLOSED,
            'closed_by' => $by?->id ?? User::factory(),
            'closed_at' => now(),
        ]);
    }
}
