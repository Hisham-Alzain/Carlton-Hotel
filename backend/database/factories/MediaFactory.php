<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * A library asset by default — no parent. Attaching is a state, not the
     * baseline, because the library is now the entry point for every upload.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->slug(2) . '.jpg';

        return [
            'mediable_type' => null,
            'mediable_id'   => null,
            'disk'          => 'public',
            'path'          => 'cms/library/' . $name,
            'file_name'     => $name,
            'alt_text'      => null,
            'title'         => null,
            'mime_type'     => 'image/jpeg',
            'size'          => $this->faker->numberBetween(1024, 512000),
            'sort_order'    => 0,
        ];
    }

    public function attachedTo(Model $parent): static
    {
        return $this->state([
            // `getMorphClass()`, never `get_class()`: Relation::morphMap() aliases
            // four models already, and a factory that wrote the FQCN would build
            // rows the ownership check in MediaService::destroy cannot match.
            'mediable_type' => $parent->getMorphClass(),
            'mediable_id'   => $parent->getKey(),
            'path'          => 'cms/' . class_basename($parent) . '/' . $this->faker->slug(2) . '.jpg',
        ]);
    }

    public function mime(string $mimeType): static
    {
        return $this->state(['mime_type' => $mimeType]);
    }

    public function described(): static
    {
        return $this->state([
            'alt_text' => ['en' => 'A lit courtyard at dusk.', 'ar' => 'ساحة مضاءة عند الغروب.'],
            'title'    => 'Courtyard at dusk',
        ]);
    }
}
