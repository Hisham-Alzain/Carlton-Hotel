<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteSettingFactory extends Factory
{
    protected $model = SiteSetting::class;

    public function definition(): array
    {
        return [
            'group'     => 'contact',
            'key'       => 'key_' . $this->faker->unique()->numberBetween(1, 999999),
            'value'     => $this->faker->sentence(),
            'type'      => SettingType::TEXT,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function translatable(): static
    {
        return $this->state([
            'value' => ['en' => $this->faker->sentence(), 'ar' => $this->faker->sentence()],
        ]);
    }
}
