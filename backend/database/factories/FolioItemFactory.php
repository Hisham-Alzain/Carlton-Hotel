<?php

namespace Database\Factories;

use App\Models\Folio;
use App\Models\FolioItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class FolioItemFactory extends Factory
{
    protected $model = FolioItem::class;

    public function definition(): array
    {
        return [
            'folio_id'    => Folio::factory(),
            'description' => 'Room charge',
            'amount_usd'  => $this->faker->randomFloat(2, 50, 500),
            'source_type' => 'reservation',
        ];
    }
}
