<?php

namespace Database\Factories;

use App\Enums\EventChecklistItem;
use App\Models\EventInquiry;
use App\Models\EventInquiryChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventInquiryChecklistItem>
 */
class EventInquiryChecklistItemFactory extends Factory
{
    protected $model = EventInquiryChecklistItem::class;

    public function definition(): array
    {
        return [
            'event_inquiry_id' => EventInquiry::factory(),
            'item'             => EventChecklistItem::CONTRACT,
            'completed_at'     => null,
            'completed_by'     => null,
        ];
    }

    public function completed(User $by): static
    {
        return $this->state([
            'completed_at' => now(),
            'completed_by' => $by->id,
        ]);
    }
}
