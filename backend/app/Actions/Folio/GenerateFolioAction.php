<?php

namespace App\Actions\Folio;

use App\Enums\FolioItemSource;
use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use App\Support\FolioLedger;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles, never rebuilds (D-06).
 *
 * Takes its own row lock on the folio on every path; inside
 * CheckOutReservationAction the same transaction already holds it and
 * re-locking is safe (D-06, RESEARCH Pitfall 2). Each computed line is keyed by
 * (source_type, source_id, source_line) and updated in place, so item uuids are
 * stable across refreshes and an unchanged refresh writes nothing.
 *
 * Only generated rows (reservation, service_booking, service_request) are ever
 * selected: manual and credit rows are never touched. A generated row a credit
 * references is frozen: it is neither deleted nor repriced nor re-described,
 * whether or not its source is still billable.
 */
class GenerateFolioAction
{
    public function handle(Reservation $reservation): array
    {
        return DB::transaction(function () use ($reservation) {
            $created = false;
            $folio   = Folio::where('reservation_id', $reservation->id)->lockForUpdate()->first();

            if ($folio === null) {
                // Race-safe: firstOrCreate falls back to a re-read on the unique
                // reservation_id index. The row is then re-selected under the lock.
                $created = Folio::firstOrCreate(
                    ['reservation_id' => $reservation->id],
                    ['status' => FolioStatus::OPEN],
                )->wasRecentlyCreated;

                $folio = Folio::where('reservation_id', $reservation->id)->lockForUpdate()->firstOrFail();
            }

            // Once settled, the folio is a closed record — regenerating would drift its total away
            // from the amount actually captured on the Payment. Return it unchanged.
            if ($folio->status === FolioStatus::SETTLED) {
                return ['data' => $this->withItems($folio), 'code' => 200];
            }

            // A checked-out stay is closed: regenerating would drift the total of
            // a forced check-out's still-open folio (Phase 3 D-06). A folio created
            // just now for a legacy checked-out stay with none is still built once.
            if ($reservation->status === ReservationStatus::CHECKED_OUT && ! $created) {
                return ['data' => $this->withItems($folio), 'code' => 200];
            }

            $lines    = $this->computeLines($reservation);
            $existing = $folio->items()
                ->whereIn('source_type', FolioItemSource::generated())
                ->withLedgerReferences()
                ->orderBy('id')
                ->get()
                ->keyBy(fn (FolioItem $item) => $this->key($item->source_type, $item->source_id, $item->source_line));

            foreach ($lines as $key => $line) {
                $row = $existing->get($key);

                if ($row === null) {
                    $folio->items()->create($line);
                    continue;
                }

                if ($row->isFrozen()) {
                    continue;
                }

                // Eloquent's decimal-aware dirty check writes (and logs) only a real change.
                $row->fill(['description' => $line['description'], 'amount_usd' => $line['amount_usd']])->save();
            }

            foreach ($existing as $key => $row) {
                if (! array_key_exists($key, $lines) && ! $row->isFrozen()) {
                    $row->delete();
                }
            }

            $folio->recalculateTotals();

            return ['data' => $this->withItems($folio->fresh()), 'code' => 200];
        });
    }

    /**
     * The billable lines of a stay, keyed like the stored rows.
     *
     * @return array<string, array{source_type: string, source_id: int, source_line: int, description: string, amount_usd: string}>
     */
    private function computeLines(Reservation $reservation): array
    {
        $lines = [];

        $add = function (FolioItemSource $source, int $sourceId, string $description, string|int|float $amount) use (&$lines): void {
            $lines[$this->key($source->value, $sourceId, 0)] = [
                'source_type' => $source->value,
                'source_id'   => $sourceId,
                'source_line' => 0,
                'description' => $description,
                'amount_usd'  => FolioLedger::normalize($amount),
            ];
        };

        $add(FolioItemSource::RESERVATION, $reservation->id, __('custom.messages.folio_room_charge'), $reservation->total_usd ?? 0);

        $bookings = ServiceBooking::where('reservation_id', $reservation->id)
            ->whereIn('status', [ServiceBookingStatus::CONFIRMED, ServiceBookingStatus::COMPLETED])
            ->with('bookable')
            ->orderBy('id')
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

            $add(FolioItemSource::SERVICE_BOOKING, $booking->id, $label, $price);
        }

        // Catalog service requests carrying a priced item. Billed here rather
        // than at request time so a request's charge follows its status (a
        // cancelled request drops off the folio on the next refresh).
        $requests = ServiceRequest::where('reservation_id', $reservation->id)
            ->whereNot('status', ServiceRequestStatus::CANCELLED)
            ->whereNotNull('service_item_id')
            ->with('serviceItem')
            ->orderBy('id')
            ->get();

        foreach ($requests as $serviceRequest) {
            $price = $serviceRequest->serviceItem?->price_usd;
            if ($price === null) {
                continue; // complimentary item — no charge
            }

            $add(
                FolioItemSource::SERVICE_REQUEST,
                $serviceRequest->id,
                $serviceRequest->serviceItem->getTranslation('name', app()->getLocale()),
                $price,
            );
        }

        return $lines;
    }

    /** A null source_id keys as an empty segment. */
    private function key(string $sourceType, ?int $sourceId, ?int $sourceLine): string
    {
        return $sourceType.':'.($sourceId ?? '').':'.($sourceLine ?? 0);
    }

    private function withItems(Folio $folio): Folio
    {
        return $folio->load(['items' => fn ($query) => $query->orderBy('id')]);
    }
}
