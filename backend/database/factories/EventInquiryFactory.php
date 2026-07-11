<?php

namespace Database\Factories;

use App\Models\EventInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'event_type'      => $this->faker->randomElement(['wedding', 'gala', 'birthday', 'other']),
            'event_date'      => $this->faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'expected_guests' => $this->faker->numberBetween(20, 300),
            'budget_usd'      => $this->faker->randomFloat(2, 1000, 50000),
            'notes'           => null,
            'status'          => EventInquiry::STATUS_NEW,
            'department'      => EventInquiry::DEPARTMENT_EVENTS,
        ];
    }

    public function corporate(): static
    {
        return $this->state(['event_type' => 'corporate', 'department' => EventInquiry::DEPARTMENT_SALES]);
    }

    public function inReview(): static
    {
        return $this->state(['status' => EventInquiry::STATUS_IN_REVIEW]);
    }
}
