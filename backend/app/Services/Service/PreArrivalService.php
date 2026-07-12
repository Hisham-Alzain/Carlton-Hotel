<?php

namespace App\Services\Service;

use App\Actions\Service\ApproveCheckInAction;
use App\Actions\Service\SubmitDocumentsAction;
use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Support\GuestEntitlement;

class PreArrivalService
{
    public function __construct(
        private readonly SubmitDocumentsAction $submitDocuments,
        private readonly ApproveCheckInAction  $approveCheckIn,
    ) {}

    public function submitDocuments(Guest $guest, array $documents): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);
        return $this->submitDocuments->handle($guest, $reservation, $documents);
    }

    public function adminIndex(): array
    {
        return ['data' => CheckInApproval::with(['reservation.documents', 'approver'])
            ->latest()
            ->paginate(20), 'code' => 200];
    }

    public function approve(Reservation $reservation, string $status, User $approver, ?string $notes): array
    {
        $result = $this->approveCheckIn->handle($reservation, $status, $approver, $notes);
        $result['data']->load('reservation.documents');
        return $result;
    }
}
