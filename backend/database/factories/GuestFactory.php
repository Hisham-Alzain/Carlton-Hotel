<?php
namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName  = $this->faker->lastName();
        return [
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'name'           => "$firstName $lastName",
            'phone'          => '+9639' . $this->faker->unique()->numerify('########'),
            'phone_country'  => 'SY',
            'email'          => $this->faker->unique()->safeEmail(),
            'preferred_locale' => 'en',
        ];
    }

    public function phoneVerified(): static
    {
        return $this->state(['phone_verified_at' => now()]);
    }

    public function emailVerified(): static
    {
        return $this->state(['email_verified_at' => now()]);
    }

    public function emailOnly(): static
    {
        return $this->state(['phone' => null, 'phone_country' => null]);
    }

    public function phoneOnly(): static
    {
        return $this->state(['email' => null]);
    }
}
