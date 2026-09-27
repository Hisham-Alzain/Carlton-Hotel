<?php

namespace Database\Factories;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\User;
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

    /** A staff-posted charge (D-04/D-05). */
    public function manual(): static
    {
        return $this->state(fn () => [
            'source_type'    => 'manual',
            'source_id'      => null,
            'description'    => 'Minibar',
            'quantity'       => 1,
            'unit_price_usd' => '25.00',
            'amount_usd'     => '25.00',
            'posted_by'      => User::factory(),
        ]);
    }

    /** A staff-posted credit: a negative line (D-04/D-05). */
    public function credit(): static
    {
        return $this->state(fn () => [
            'source_type'    => 'credit',
            'source_id'      => null,
            'description'    => 'Goodwill credit',
            'quantity'       => 1,
            'unit_price_usd' => '10.00',
            'amount_usd'     => '-10.00',
            'reason'         => 'Service recovery',
            'posted_by'      => User::factory(),
        ]);
    }
}
