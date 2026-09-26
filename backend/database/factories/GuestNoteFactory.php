<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\GuestNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestNoteFactory extends Factory
{
    protected $model = GuestNote::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'user_id'  => User::factory(),
            'body'     => $this->faker->sentence(),
        ];
    }
}
