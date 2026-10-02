<?php

namespace Database\Factories;

use App\Enums\TicketRecoveryType;
use App\Models\FolioItem;
use App\Models\TicketAction;
use App\Models\TicketRecovery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRecovery>
 */
class TicketRecoveryFactory extends Factory
{
    protected $model = TicketRecovery::class;

    public function definition(): array
    {
        return [
            'ticket_action_id' => TicketAction::factory()->recovery(),
            'type'             => TicketRecoveryType::APOLOGY,
            'description'      => $this->faker->sentence(6),
            'amount_usd'       => null,
        ];
    }

    /** Links a (negative) folio credit line; amount is its absolute value. */
    public function folioCredit(FolioItem $item): static
    {
        return $this->state([
            'type'          => TicketRecoveryType::FOLIO_CREDIT,
            'folio_item_id' => $item->id,
            'amount_usd'    => ltrim((string) $item->amount_usd, '-'),
        ]);
    }
}
