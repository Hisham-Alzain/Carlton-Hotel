<?php

namespace App\Actions\Folio;

use App\Enums\FolioStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class GenerateFolioAction
{
    public function handle(Reservation $reservation): array
    {
        return DB::transaction(function () use ($reservation) {
            $folio = Folio::firstOrCreate(
                ['reservation_id' => $reservation->id],
                ['status' => FolioStatus::OPEN]
            );

            // Once settled, the folio is a closed record — regenerating would drift its total away
            // from the amount actually captured on the Payment. Return it unchanged.
            if ($folio->status === FolioStatus::SETTLED) {
                return ['data' => $folio->load('items'), 'code' => 200];
            }

            // Regenerate line items fresh each call — folio reflects current charges, not accumulated history.
            $folio->items()->delete();

            $lines = [[
                'description' => __('custom.messages.folio_room_charge'),
                'amount_usd'  => (float) $reservation->total_usd,
                'source_type' => 'reservation',
                'source_id'   => $reservation->id,
            ]];

            $bookings = ServiceBooking::where('reservation_id', $reservation->id)
                ->whereIn('status', [ServiceBookingStatus::CONFIRMED, ServiceBookingStatus::COMPLETED])
                ->with('bookable')
                ->get();

            foreach ($bookings as $booking) {
                $price = $booking->bookable?->price_usd ?? null;
                if ($price === null) {
                    continue; // e.g. restaurant_table bookings carry no charge
                }

                $bookable = $booking->bookable;
                $label = method_exists($bookable, 'getTranslation')
                    ? $bookable->getTranslation('name', app()->getLocale())
                    : $booking->bookable_type;

                $lines[] = [
                    'description' => $label,
                    'amount_usd'  => (float) $price,
                    'source_type' => 'service_booking',
                    'source_id'   => $booking->id,
                ];
            }

            // Catalog service requests carrying a priced item. Billed here rather
            // than at request time because this action rebuilds line items from
            // scratch on every call — a row written at request time would be
            // deleted on the next regeneration.
            $requests = ServiceRequest::where('reservation_id', $reservation->id)
                ->whereNot('status', ServiceRequestStatus::CANCELLED)
                ->whereNotNull('service_item_id')
                ->with('serviceItem')
                ->get();

            foreach ($requests as $serviceRequest) {
                $price = $serviceRequest->serviceItem?->price_usd;
                if ($price === null) {
                    continue; // complimentary item — no charge
                }

                $lines[] = [
                    'description' => $serviceRequest->serviceItem->getTranslation('name', app()->getLocale()),
                    'amount_usd'  => (float) $price,
                    'source_type' => 'service_request',
                    'source_id'   => $serviceRequest->id,
                ];
            }

            foreach ($lines as $line) {
                $folio->items()->create($line);
            }

            $total = array_sum(array_column($lines, 'amount_usd'));
            $folio->update(['subtotal_usd' => $total, 'total_usd' => $total]);

            return ['data' => $folio->fresh()->load('items'), 'code' => 200];
        });
    }
}
