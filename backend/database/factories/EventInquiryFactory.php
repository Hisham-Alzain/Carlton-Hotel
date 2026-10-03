<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\EventDepositStatus;
use App\Enums\EventInquiryStatus;
use App\Enums\EventType;
use App\Models\EventInquiry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventInquiryFactory extends Factory
{
    protected $model = EventInquiry::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->name(),
            'email'           => $this->faker->unique()->safeEmail(),
            'phone'           => null,
            'company'         => $this->faker->company(),
            'event_type'      => $this->faker->randomElement([EventType::WEDDING, EventType::GALA, EventType::BIRTHDAY, EventType::OTHER]),
            'event_date'      => $this->faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'expected_guests' => $this->faker->numberBetween(20, 300),
            'budget_usd'      => $this->faker->randomFloat(2, 1000, 50000),
            'notes'           => null,
            'status'          => EventInquiryStatus::NEW,
            'department'      => Department::EVENTS,
        ];
    }

    public function corporate(): static
    {
        return $this->state(['event_type' => EventType::CORPORATE, 'department' => Department::SALES]);
    }

    public function inReview(): static
    {
        return $this->state(['status' => EventInquiryStatus::IN_REVIEW]);
    }

    public function quoted(): static
    {
        return $this->state(['status' => EventInquiryStatus::QUOTED]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => EventInquiryStatus::CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => EventInquiryStatus::CANCELLED]);
    }

    /**
     * A paid deposit, ledger-backed like `RecordEventDepositAction` writes it:
     * one completed cash payment (FQCN payable, PR-8) plus the status flip.
     */
    public function withDeposit(?User $by = null, string $amount = '500.00'): static
    {
        return $this->afterCreating(function (EventInquiry $inquiry) use ($by, $amount): void {
            Payment::create([
                'payable_type'    => EventInquiry::class,
                'payable_id'      => $inquiry->id,
                'method'          => 'cash',
                'amount_usd'      => $amount,
                'recorded_by'     => ($by ?? User::factory()->create())->id,
                'note'            => null,
                'status'          => 'completed',
                'idempotency_key' => 'factory-' . Str::uuid(),
            ]);

            $inquiry->forceFill([
                'deposit_status'  => EventDepositStatus::PAID,
                'deposit_paid_at' => now(),
            ])->save();
        });
    }
}
