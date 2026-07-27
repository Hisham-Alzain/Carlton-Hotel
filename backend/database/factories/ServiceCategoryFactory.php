<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\ServiceCategoryKind;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        return [
            'code'        => $this->faker->unique()->slug(2),
            'name'        => ['en' => $this->faker->words(2, true), 'ar' => 'خدمة'],
            'description' => ['en' => $this->faker->sentence(), 'ar' => 'وصف'],
            'kind'        => ServiceCategoryKind::CATALOG,
            'department'  => Department::CONCIERGE,
            'icon'        => 'concierge',
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    public function direct(): static
    {
        return $this->state(['kind' => ServiceCategoryKind::DIRECT]);
    }

    public function link(string $target = 'dining'): static
    {
        return $this->state([
            'kind'        => ServiceCategoryKind::LINK,
            'department'  => null,
            'link_target' => $target,
        ]);
    }

    public function toggle(): static
    {
        return $this->state(['kind' => ServiceCategoryKind::TOGGLE, 'department' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
