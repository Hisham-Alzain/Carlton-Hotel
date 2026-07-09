<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->words(2, true));
        return [
            'slug'       => $slug,
            'title'      => ['en' => $this->faker->sentence(3), 'ar' => $this->faker->sentence(3)],
            'content'    => ['en' => $this->faker->paragraphs(3, true), 'ar' => $this->faker->paragraphs(3, true)],
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
