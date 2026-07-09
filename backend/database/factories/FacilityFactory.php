<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        return [
            'name'        => ['en' => $this->faker->unique()->word(), 'ar' => $this->faker->unique()->word()],
            'description' => ['en' => $this->faker->paragraph(), 'ar' => $this->faker->paragraph()],
            'location'    => ['en' => 'Ground Floor', 'ar' => 'الطابق الأرضي'],
            'hours'       => ['en' => '6am–10pm', 'ar' => '٦ص–١٠م'],
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
