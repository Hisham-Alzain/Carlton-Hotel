<?php

namespace Database\Factories;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryItemFactory extends Factory
{
    protected $model = GalleryItem::class;

    public function definition(): array
    {
        return [
            // A gallery item cannot exist without a chip (the FK is NOT NULL), so
            // the factory makes one rather than leaving the caller to.
            'gallery_category_id' => GalleryCategory::factory(),
            'caption'             => ['en' => $this->faker->sentence(6), 'ar' => 'صورة ' . $this->faker->word()],
            'is_active'           => true,
            'sort_order'          => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
