<?php

namespace Database\Factories;

use App\Models\NightAuditState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NightAuditState>
 */
class NightAuditStateFactory extends Factory
{
    protected $model = NightAuditState::class;

    public function definition(): array
    {
        return [
            'singleton'             => 1,
            'current_business_date' => '2026-10-01',
            'last_closed_date'      => null,
        ];
    }

    public function on(string $date, ?string $lastClosed = null): static
    {
        return $this->state([
            'current_business_date' => $date,
            'last_closed_date'      => $lastClosed,
        ]);
    }
}
