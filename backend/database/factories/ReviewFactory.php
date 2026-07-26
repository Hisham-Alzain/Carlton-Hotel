<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Review;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'guest_id'         => Guest::factory(),
            'reviewable_type'  => RoomType::class,
            'reviewable_id'    => RoomType::factory(),
            'reservation_id'   => null,
            'rating'           => $this->faker->numberBetween(1, 5),
            'comment'          => $this->faker->sentence(),
            'is_verified_stay' => false,
            'is_published'     => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function for_(\Illuminate\Database\Eloquent\Model $reviewable): static
    {
        return $this->state([
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id'   => $reviewable->getKey(),
        ]);
    }
}
