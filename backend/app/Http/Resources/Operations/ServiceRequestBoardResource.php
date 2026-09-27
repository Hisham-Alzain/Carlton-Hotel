<?php

namespace App\Http\Resources\Operations;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/**
 * A row of the staff service-request board (Phase 6, D-16). Staff-only: the
 * guest appears as uuid and name only (no phone, email or documents).
 *
 * `category_code` is the category code snapshotted into `type` when the
 * request came from the catalogue, else null (legacy free-string request);
 * no category query (FA-6.06-1). `reservation.room_number` comes from the
 * `withRoomNumber()` subselect. No queries here: ServiceRequestBoardService
 * loads every relation.
 */
class ServiceRequestBoardResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'type'              => $this->type,
            'category_code'     => $this->service_item_id !== null ? $this->type : null,
            'department'        => $this->department?->value,
            'status'            => $this->status?->value,
            'priority'          => $this->priority?->value,
            'notes'             => $this->notes,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
            'guest'             => $this->whenLoaded('guest', fn () => $this->guest ? [
                'uuid' => $this->guest->uuid,
                'name' => $this->guest->name,
            ] : null),
            'reservation'       => $this->whenLoaded('reservation', fn () => $this->reservation ? [
                'uuid'         => $this->reservation->uuid,
                'booking_code' => $this->reservation->booking_code,
                'check_out'    => $this->reservation->check_out?->toDateString(),
                'room_number'  => $this->room_number !== null ? (string) $this->room_number : null,
            ] : null),
            'service_item'      => $this->whenLoaded('serviceItem', fn () => $this->serviceItem ? [
                'uuid'             => $this->serviceItem->uuid,
                'name'             => $this->serviceItem->getTranslations('name'),
                'expected_minutes' => $this->serviceItem->expected_minutes,
                'price_usd'        => $this->serviceItem->price_usd,
            ] : null),
            'assigned_user'     => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'uuid' => $this->assignedUser->uuid,
                'name' => $this->assignedUser->name,
            ] : null),
            'housekeeping_task' => $this->whenLoaded('housekeepingTask', fn () => $this->housekeepingTask ? [
                'uuid'   => $this->housekeepingTask->uuid,
                'status' => $this->housekeepingTask->status->value,
            ] : null),
        ];
    }
}
