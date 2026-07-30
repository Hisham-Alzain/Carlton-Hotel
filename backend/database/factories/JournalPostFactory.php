<?php

namespace Database\Factories;

use App\Models\JournalPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JournalPostFactory extends Factory
{
    protected $model = JournalPost::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'slug'         => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(1, 999999),
            'title'        => ['en' => $title, 'ar' => $this->faker->sentence(4)],
            'excerpt'      => ['en' => $this->faker->sentence(12), 'ar' => $this->faker->sentence(12)],
            'body'         => ['en' => $this->faker->paragraphs(3, true), 'ar' => $this->faker->paragraphs(3, true)],
            'category'     => ['en' => 'Hotel News', 'ar' => 'أخبار الفندق'],
            'published_on' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'is_active'    => true,
            'sort_order'   => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * A post an editor dated ahead of today. It must still be publicly visible —
     * `published_on` is a display date, not a schedule.
     */
    public function futureDated(): static
    {
        return $this->state(['published_on' => now()->addMonth()->toDateString()]);
    }

    public function publishedOn(string $date): static
    {
        return $this->state(['published_on' => $date]);
    }
}
