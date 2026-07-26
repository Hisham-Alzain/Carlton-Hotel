<?php
namespace Database\Factories;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    public function definition(): array
    {
        return [
            'identifier'  => '+9639' . $this->faker->numerify('########'),
            'channel'     => OtpChannel::SMS,
            'code_hash'   => Hash::make('123456'),
            'purpose'     => OtpPurpose::LOGIN,
            'attempts'    => 0,
            'expires_at'  => now()->addMinutes(5),
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(['consumed_at' => now()]);
    }

    public function locked(): static
    {
        return $this->state(['attempts' => 5]);
    }

    public function emailChannel(): static
    {
        return $this->state([
            'channel'    => OtpChannel::EMAIL,
            'identifier' => $this->faker->safeEmail(),
        ]);
    }
}
