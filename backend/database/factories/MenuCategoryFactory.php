<?php

namespace Database\Factories;

use App\Models\DiningVenue;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'dining_venue_id' => DiningVenue::factory(),
            'slug'            => Str::slug($name),
            'name'            => ['en' => $name, 'ar' => 'قسم القائمة'],
            'sort_order'      => $this->faker->numberBetween(0, 10),
            'is_active'       => true,
        ];
    }

    public function forVenue(DiningVenue $venue): static
    {
        return $this->state(['dining_venue_id' => $venue->id]);
    }
}
