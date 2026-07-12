<?php

namespace App\Actions\Folio;

use App\Models\Folio;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ApproveFolioAction
{
    public function __construct(private readonly GenerateFolioAction $generateFolio) {}

    public function handle(Reservation $reservation): array
    {
        return DB::transaction(function () use ($reservation) {
            $result = $this->generateFolio->handle($reservation);
            $folio  = $result['data'];

            $folio->update(['approved_by_guest_at' => now()]);
            $reservation->update(['status' => Reservation::STATUS_CHECKED_OUT]);

            return ['data' => $folio->fresh()->load('items'), 'code' => 200];
        });
    }
}
