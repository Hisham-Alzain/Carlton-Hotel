<?php

namespace App\Actions\Tickets;

use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\TicketActionType;
use App\Enums\TicketCategory;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single creator of tickets from the staff desk (Phase 7, D-04, D-05,
 * D-11, D-12). Source is always STAFF, status OPEN, the creator is the actor
 * and the chatbot conversation link is never set here.
 *
 * Guest linking (D-05): a reservation without a guest derives the guest from
 * it; a guest that disagrees with the reservation's guest is a 422 on
 * `guest_uuid`. The room is independent of the reservation.
 *
 * Lock invariant: the ticket row lock is a leaf lock — no ticket writer ever
 * holds it while locking a room, task, service request or folio. Create takes
 * no lock (the row is new).
 *
 * `$data`: subject, description?, category (TicketCategory),
 * priority? (ServiceRequestPriority), department? (Department),
 * guest? (Guest), reservation? (Reservation), room? (Room).
 */
class CreateTicketAction
{
    public function handle(array $data, User $actor): array
    {
        /** @var TicketCategory $category */
        $category    = $data['category'];
        $reservation = $data['reservation'] ?? null;
        $guest       = $data['guest'] ?? null;
        $room        = $data['room'] ?? null;

        $guestId = $this->resolveGuestId($guest, $reservation);

        /** @var ServiceRequestPriority $priority */
        $priority   = $data['priority'] ?? ServiceRequestPriority::NORMAL;
        $department = $data['department'] ?? Department::forTicketCategory($category);

        return DB::transaction(function () use ($data, $actor, $category, $reservation, $room, $guestId, $priority, $department) {
            $ticket = Ticket::create([
                'subject'        => $data['subject'],
                'description'    => $data['description'] ?? null,
                'category'       => $category,
                'status'         => TicketStatus::OPEN,
                'priority'       => $priority->toTicketScale(),
                'department'     => $department,
                'source'         => TicketSource::STAFF,
                'guest_id'       => $guestId,
                'reservation_id' => $reservation?->getKey(),
                'room_id'        => $room?->getKey(),
                'created_by'     => $actor->getKey(),
            ]);

            TicketAction::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $actor->getKey(),
                'type'        => TicketActionType::CREATED,
                'from_status' => null,
                'to_status'   => TicketStatus::OPEN->value,
            ]);

            TicketChanged::dispatch($ticket);

            return ['data' => $ticket, 'code' => 201];
        }, 3);
    }

    private function resolveGuestId(?Guest $guest, ?Reservation $reservation): ?int
    {
        if ($reservation === null) {
            return $guest?->getKey();
        }

        if ($guest !== null && $guest->getKey() !== $reservation->guest_id) {
            throw ValidationException::withMessages([
                'guest_uuid' => [__('custom.validation.ticket_guest_mismatch')],
            ]);
        }

        return $reservation->guest_id;
    }
}
