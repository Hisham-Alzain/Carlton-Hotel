<?php

namespace Tests\Unit\Folio;

use App\Actions\Folio\GenerateFolioAction;
use App\Enums\FolioStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioItemDispute;
use App\Models\Reservation;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/**
 * Phase 5 (D-06, D-09): GenerateFolioAction reconciles, never rebuilds. Rows
 * keep their uuid; manual and credit rows are never touched; a generated row a
 * credit or a dispute references is frozen (never deleted, repriced or
 * re-described).
 */
class GenerateFolioReconcileTest extends TestCase
{
    use RefreshDatabase, RecordsRowLocks;

    private function generate(Reservation $reservation): Folio
    {
        return app(GenerateFolioAction::class)->handle($reservation->fresh())['data'];
    }

    private function stay(string $total = '300.00'): Reservation
    {
        return Reservation::factory()->checkedIn()->create(['total_usd' => $total]);
    }

    private function pricedRequest(Reservation $reservation, float $price = 15.00): ServiceRequest
    {
        return ServiceRequest::factory()->create([
            'guest_id'        => $reservation->guest_id,
            'reservation_id'  => $reservation->id,
            'service_item_id' => ServiceItem::factory()->priced($price)->create()->id,
        ]);
    }

    /** @return array{insert:int, update:int, delete:int} writes to folio_items during $run */
    private function itemWrites(callable $run): array
    {
        $counts = ['insert' => 0, 'update' => 0, 'delete' => 0];
        $on     = true;
        DB::listen(function ($event) use (&$counts, &$on) {
            if (! $on) {
                return;
            }
            $sql = strtolower($event->sql);
            if (str_starts_with($sql, 'insert into "folio_items"')) {
                $counts['insert']++;
            } elseif (str_starts_with($sql, 'update "folio_items"')) {
                $counts['update']++;
            } elseif (str_starts_with($sql, 'delete from "folio_items"')) {
                $counts['delete']++;
            }
        });
        $run();
        $on = false;

        return $counts;
    }

    public function test_first_build_creates_keyed_generated_rows(): void
    {
        $reservation = $this->stay('300.00');
        $request     = $this->pricedRequest($reservation, 15);

        $folio = $this->generate($reservation);

        $this->assertSame('315.00', $folio->total_usd);
        $this->assertSame('315.00', $folio->subtotal_usd);
        $this->assertDatabaseHas('folio_items', ['folio_id' => $folio->id, 'source_type' => 'reservation', 'source_id' => $reservation->id, 'source_line' => 0]);
        $this->assertDatabaseHas('folio_items', ['folio_id' => $folio->id, 'source_type' => 'service_request', 'source_id' => $request->id, 'source_line' => 0]);
    }

    public function test_unchanged_refresh_writes_nothing_and_keeps_uuids(): void
    {
        $reservation = $this->stay();
        $this->pricedRequest($reservation);
        $before = $this->generate($reservation)->items->pluck('uuid')->all();

        $writes = $this->itemWrites(fn () => $this->generate($reservation));

        $this->assertSame(['insert' => 0, 'update' => 0, 'delete' => 0], $writes);
        $this->assertSame($before, $this->generate($reservation)->items->pluck('uuid')->all());
    }

    public function test_price_change_updates_the_row_in_place(): void
    {
        $reservation = $this->stay('300.00');
        $uuid        = $this->generate($reservation)->items->first()->uuid;

        $reservation->update(['total_usd' => '320.00']);
        $folio = $this->generate($reservation);

        $this->assertSame($uuid, $folio->items->first()->uuid);
        $this->assertSame('320.00', $folio->items->first()->amount_usd);
        $this->assertSame('320.00', $folio->total_usd);
    }

    public function test_manual_and_credit_rows_survive_refresh_with_correct_total(): void
    {
        $reservation = $this->stay('300.00');
        $folio       = $this->generate($reservation);
        FolioItem::factory()->manual()->create(['folio_id' => $folio->id, 'amount_usd' => '25.00']);
        FolioItem::factory()->credit()->create(['folio_id' => $folio->id, 'amount_usd' => '-10.00']);

        $refreshed = $this->generate($reservation);

        $this->assertCount(3, $refreshed->items);
        $this->assertSame('315.00', $refreshed->total_usd);
    }

    public function test_cancelled_unreferenced_generated_row_is_deleted(): void
    {
        $reservation = $this->stay('300.00');
        $request     = $this->pricedRequest($reservation, 15);
        $this->generate($reservation);

        $request->update(['status' => ServiceRequestStatus::CANCELLED]);
        $folio = $this->generate($reservation);

        $this->assertCount(1, $folio->items);
        $this->assertSame('300.00', $folio->total_usd);
        $this->assertDatabaseMissing('folio_items', ['source_type' => 'service_request', 'source_id' => $request->id]);
    }

    public function test_credited_generated_row_is_frozen_through_cancellation(): void
    {
        $reservation = $this->stay('300.00');
        $request     = $this->pricedRequest($reservation, 15);
        $folio       = $this->generate($reservation);
        $charge      = $folio->items->firstWhere('source_type', 'service_request');
        FolioItem::factory()->credit()->create([
            'folio_id' => $folio->id, 'amount_usd' => '-5.00', 'unit_price_usd' => '5.00', 'reverses_item_id' => $charge->id,
        ]);

        $request->update(['status' => ServiceRequestStatus::CANCELLED]);
        $folio = $this->generate($reservation);

        $this->assertDatabaseHas('folio_items', ['id' => $charge->id, 'uuid' => $charge->uuid, 'amount_usd' => '15.00']);
        $this->assertSame('310.00', $folio->total_usd);
    }

    public function test_frozen_row_is_never_repriced_or_redescribed(): void
    {
        $reservation = $this->stay('300.00');
        $folio       = $this->generate($reservation);
        $room        = $folio->items->first();
        FolioItem::factory()->credit()->create([
            'folio_id' => $folio->id, 'amount_usd' => '-50.00', 'unit_price_usd' => '50.00', 'reverses_item_id' => $room->id,
        ]);
        $room->update(['description' => 'As billed']);

        $reservation->update(['total_usd' => '999.00']);
        $folio = $this->generate($reservation);

        $stored = FolioItem::find($room->id);
        $this->assertSame('300.00', $stored->amount_usd);
        $this->assertSame('As billed', $stored->description);
        $this->assertSame('250.00', $folio->total_usd);
    }

    public function test_disputed_generated_row_is_frozen_through_cancellation_and_price_change(): void
    {
        $reservation = $this->stay('300.00');
        $request     = $this->pricedRequest($reservation, 15);
        $folio       = $this->generate($reservation);
        $charge      = $folio->items->firstWhere('source_type', 'service_request');
        // Any dispute freezes the row, whatever its status (D-09).
        FolioItemDispute::factory()->resolved()->create(['folio_item_id' => $charge->id, 'guest_id' => $reservation->guest_id]);

        $request->serviceItem->update(['price_usd' => 99]);
        $this->generate($reservation);
        $this->assertSame('15.00', FolioItem::find($charge->id)->amount_usd);

        $request->update(['status' => ServiceRequestStatus::CANCELLED]);
        $folio = $this->generate($reservation);

        $this->assertDatabaseHas('folio_items', ['id' => $charge->id, 'amount_usd' => '15.00']);
        $this->assertSame('315.00', $folio->total_usd);
    }

    public function test_settled_folio_is_returned_unchanged(): void
    {
        $reservation = $this->stay('300.00');
        $folio       = $this->generate($reservation);
        $folio->update(['status' => FolioStatus::SETTLED, 'settled_at' => now()]);
        $reservation->update(['total_usd' => '500.00']);

        $writes = $this->itemWrites(fn () => $this->generate($reservation));

        $this->assertSame(['insert' => 0, 'update' => 0, 'delete' => 0], $writes);
        $this->assertSame('300.00', $folio->fresh()->total_usd);
    }

    public function test_checked_out_stay_is_not_regenerated(): void
    {
        $reservation = $this->stay('300.00');
        $this->generate($reservation);
        $reservation->update(['status' => 'checked_out', 'total_usd' => '500.00']);

        $folio = $this->generate($reservation);

        $this->assertSame('300.00', $folio->total_usd);
    }

    public function test_every_generate_path_locks_the_folio_row(): void
    {
        // First build.
        $reservation = $this->stay();
        $this->assertLocksRow('folios', fn () => $this->generate($reservation));
        // Refresh.
        $this->assertLocksRow('folios', fn () => $this->generate($reservation));
        // Checked out.
        $reservation->update(['status' => 'checked_out']);
        $this->assertLocksRow('folios', fn () => $this->generate($reservation));
        // Settled.
        Folio::where('reservation_id', $reservation->id)->update(['status' => FolioStatus::SETTLED->value, 'settled_at' => now()]);
        $this->assertLocksRow('folios', fn () => $this->generate($reservation));
    }
}
