<?php

namespace App\Services\Folio;

use App\Actions\Folio\ApproveFolioAction;
use App\Actions\Folio\GenerateFolioAction;
use App\Actions\Folio\SettleFolioAction;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Support\GuestEntitlement;

class FolioService
{
    public function __construct(
        private readonly GenerateFolioAction $generate,
        private readonly ApproveFolioAction  $approve,
        private readonly SettleFolioAction   $settle,
    ) {}

    public function myFolio(Guest $guest): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);
        return $this->generate->handle($reservation);
    }

    public function approveMyFolio(Guest $guest): array
    {
        $reservation = GuestEntitlement::currentReservation($guest);
        return $this->approve->handle($reservation);
    }

    public function adminGenerate(Reservation $reservation): array
    {
        return $this->generate->handle($reservation);
    }

    public function adminSettle(Folio $folio, array $data, User $recorder): array
    {
        return $this->settle->handle(
            $folio,
            $data['method'],
            (float) $data['amount_usd'],
            $recorder,
            $data['note'] ?? null,
        );
    }
}
