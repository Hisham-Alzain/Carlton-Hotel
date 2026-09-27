<?php

namespace Database\Factories;

use App\Enums\FolioDisputeStatus;
use App\Models\FolioItem;
use App\Models\FolioItemDispute;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FolioItemDisputeFactory extends Factory
{
    protected $model = FolioItemDispute::class;

    public function definition(): array
    {
        return [
            'folio_item_id' => FolioItem::factory(),
            'status'        => FolioDisputeStatus::OPEN,
            'reason'        => 'Charged twice',
            'guest_id'      => Guest::factory(),
            'user_id'       => null,
        ];
    }

    public function resolved(): static
    {
        return $this->closed(FolioDisputeStatus::RESOLVED);
    }

    public function rejected(): static
    {
        return $this->closed(FolioDisputeStatus::REJECTED);
    }

    public function byStaff(): static
    {
        return $this->state(fn () => ['guest_id' => null, 'user_id' => User::factory()]);
    }

    private function closed(FolioDisputeStatus $status): static
    {
        return $this->state(fn () => [
            'status'          => $status,
            'resolved_by'     => User::factory(),
            'resolved_at'     => now(),
            'resolution_note' => 'Checked with the outlet; charge confirmed.',
        ]);
    }
}
