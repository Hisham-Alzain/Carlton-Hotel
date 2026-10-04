<?php

namespace Tests\Feature\NightAudit;

use App\Enums\FolioDisputeStatus;
use App\Enums\FolioStatus;
use App\Enums\NightAuditCheckType;
use App\Enums\ReservationStatus;
use App\Enums\TicketStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioItemDispute;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\Ticket;
use App\Support\NightAuditEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CountsDomainQueries;
use Tests\TestCase;

/**
 * Phase 9 (D-05, D-06, D-09, D-10, D-22): what each of the five night-audit
 * checks counts, its bounded public evidence and its constant query count.
 */
class NightAuditEvaluatorTest extends TestCase
{
    use CountsDomainQueries, RefreshDatabase;

    private const D = '2026-10-10';

    private function evaluate(): array
    {
        return app(NightAuditEvaluator::class)->evaluate(self::D);
    }

    private function check(NightAuditCheckType $type): array
    {
        return $this->evaluate()[$type->value];
    }

    private function reservation(ReservationStatus $status, string $checkIn = '2026-10-07', string $checkOut = self::D, array $extra = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'status'    => $status,
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
        ], $extra));
    }

    private function folio(Reservation $reservation, FolioStatus $status = FolioStatus::OPEN): Folio
    {
        return Folio::factory()->create(['reservation_id' => $reservation->id, 'status' => $status]);
    }

    private function arrival(ReservationStatus $status = ReservationStatus::CONFIRMED, array $roomIds = [null]): Reservation
    {
        $reservation = $this->reservation($status, self::D, '2026-10-12');
        foreach ($roomIds as $roomId) {
            ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_id' => $roomId]);
        }

        return $reservation;
    }

    // ---- result shape -------------------------------------------------------

    public function test_returns_all_five_types_in_enum_order(): void
    {
        $result = $this->evaluate();

        $this->assertSame(array_map(fn ($t) => $t->value, NightAuditCheckType::cases()), array_keys($result));
        foreach ($result as $entry) {
            $this->assertSame(['issue_count' => 0, 'evidence' => [], 'evidence_truncated' => false], $entry);
        }
    }

    // ---- unsettled departures -----------------------------------------------

    public function test_departure_without_folio_is_counted(): void
    {
        $r = $this->reservation(ReservationStatus::CHECKED_IN);

        $result = $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES);
        $this->assertSame(1, $result['issue_count']);
        $this->assertSame([['reservation_uuid' => $r->uuid, 'booking_code' => $r->booking_code]], $result['evidence']);
    }

    public function test_departure_with_zero_balance_open_folio_is_counted(): void
    {
        $this->folio($this->reservation(ReservationStatus::CONFIRMED));

        $this->assertSame(1, $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['issue_count']);
    }

    public function test_departure_with_settled_folio_is_excluded(): void
    {
        $this->folio($this->reservation(ReservationStatus::CHECKED_OUT), FolioStatus::SETTLED);

        $this->assertSame(0, $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['issue_count']);
    }

    public function test_express_checked_out_departure_with_open_folio_is_counted(): void
    {
        $this->folio($this->reservation(ReservationStatus::CHECKED_OUT));

        $this->assertSame(1, $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['issue_count']);
    }

    public function test_pending_and_cancelled_departures_are_excluded(): void
    {
        $this->reservation(ReservationStatus::PENDING);
        $this->reservation(ReservationStatus::PENDING_VERIFICATION);
        $this->reservation(ReservationStatus::CANCELLED);

        $this->assertSame(0, $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['issue_count']);
    }

    public function test_departure_on_another_date_is_excluded(): void
    {
        $this->reservation(ReservationStatus::CHECKED_IN, '2026-10-07', '2026-10-11');
        $this->reservation(ReservationStatus::CHECKED_OUT, '2026-10-05', '2026-10-09');

        $this->assertSame(0, $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['issue_count']);
    }

    public function test_multi_room_departure_is_counted_once(): void
    {
        $r = $this->reservation(ReservationStatus::CHECKED_IN);
        ReservationRoom::factory()->count(3)->create(['reservation_id' => $r->id]);
        $this->folio($r);

        $result = $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES);
        $this->assertSame(1, $result['issue_count']);
        $this->assertCount(1, $result['evidence']);
    }

    public function test_sqlite_datetime_storage_still_matches(): void
    {
        $r = $this->reservation(ReservationStatus::CHECKED_IN);
        $a = $this->arrival();
        DB::table('reservations')->where('id', $r->id)->update(['check_out' => self::D . ' 00:00:00']);
        DB::table('reservations')->where('id', $a->id)->update(['check_in' => self::D . ' 00:00:00']);

        $this->assertSame(1, $this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['issue_count']);
        $this->assertSame(1, $this->check(NightAuditCheckType::UNASSIGNED_ARRIVALS)['issue_count']);
    }

    public function test_departure_evidence_is_ordered_by_booking_code(): void
    {
        foreach (['CARL-ZZZ', 'CARL-AAA', 'CARL-MMM'] as $code) {
            $this->reservation(ReservationStatus::CHECKED_IN, '2026-10-07', self::D, ['booking_code' => $code]);
        }

        $codes = array_column($this->check(NightAuditCheckType::UNSETTLED_DEPARTURES)['evidence'], 'booking_code');
        $this->assertSame(['CARL-AAA', 'CARL-MMM', 'CARL-ZZZ'], $codes);
    }

    // ---- unassigned arrivals ------------------------------------------------

    public function test_arrival_with_one_unassigned_line_is_counted_once(): void
    {
        $room = Room::factory()->create();
        $r = $this->arrival(ReservationStatus::CONFIRMED, [$room->id, null]);

        $result = $this->check(NightAuditCheckType::UNASSIGNED_ARRIVALS);
        $this->assertSame(1, $result['issue_count']);
        $this->assertSame([['reservation_uuid' => $r->uuid, 'booking_code' => $r->booking_code]], $result['evidence']);
    }

    public function test_arrival_without_lines_is_counted(): void
    {
        $this->arrival(ReservationStatus::CHECKED_IN, []);

        $this->assertSame(1, $this->check(NightAuditCheckType::UNASSIGNED_ARRIVALS)['issue_count']);
    }

    public function test_fully_assigned_arrival_is_excluded(): void
    {
        $this->arrival(ReservationStatus::CONFIRMED, [Room::factory()->create()->id, Room::factory()->create()->id]);

        $this->assertSame(0, $this->check(NightAuditCheckType::UNASSIGNED_ARRIVALS)['issue_count']);
    }

    public function test_checked_out_cancelled_and_pending_arrivals_are_excluded(): void
    {
        $this->arrival(ReservationStatus::CHECKED_OUT);
        $this->arrival(ReservationStatus::CANCELLED);
        $this->arrival(ReservationStatus::PENDING);
        $this->arrival(ReservationStatus::PENDING_VERIFICATION);

        $this->assertSame(0, $this->check(NightAuditCheckType::UNASSIGNED_ARRIVALS)['issue_count']);
    }

    public function test_arrival_on_another_date_is_excluded(): void
    {
        $r = $this->reservation(ReservationStatus::CONFIRMED, '2026-10-11', '2026-10-12');
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_id' => null]);

        $this->assertSame(0, $this->check(NightAuditCheckType::UNASSIGNED_ARRIVALS)['issue_count']);
    }

    // ---- dirty rooms --------------------------------------------------------

    public function test_dirty_live_active_room_is_counted(): void
    {
        $room = Room::factory()->create(['status' => 'dirty', 'number' => '204']);

        $result = $this->check(NightAuditCheckType::DIRTY_ROOMS);
        $this->assertSame(1, $result['issue_count']);
        $this->assertSame([['room_uuid' => $room->uuid, 'number' => '204']], $result['evidence']);
    }

    public function test_trashed_inactive_maintenance_and_available_rooms_are_excluded(): void
    {
        Room::factory()->create(['status' => 'dirty'])->delete();
        Room::factory()->inactive()->create(['status' => 'dirty']);
        Room::factory()->create(['status' => 'maintenance']);
        Room::factory()->create(['status' => 'available']);

        $this->assertSame(0, $this->check(NightAuditCheckType::DIRTY_ROOMS)['issue_count']);
    }

    public function test_evidence_is_capped_at_twenty_and_ordered(): void
    {
        foreach (range(25, 1) as $n) {
            Room::factory()->create(['status' => 'dirty', 'number' => sprintf('%03d', $n)]);
        }

        $result = $this->check(NightAuditCheckType::DIRTY_ROOMS);
        $this->assertSame(25, $result['issue_count']);
        $this->assertCount(20, $result['evidence']);
        $this->assertTrue($result['evidence_truncated']);
        $this->assertSame(
            array_map(fn ($n) => sprintf('%03d', $n), range(1, 20)),
            array_column($result['evidence'], 'number'),
        );
    }

    public function test_exactly_twenty_issues_is_not_truncated(): void
    {
        foreach (range(1, 20) as $n) {
            Room::factory()->create(['status' => 'dirty', 'number' => sprintf('%03d', $n)]);
        }

        $result = $this->check(NightAuditCheckType::DIRTY_ROOMS);
        $this->assertSame(20, $result['issue_count']);
        $this->assertFalse($result['evidence_truncated']);
    }

    // ---- high-priority tickets ----------------------------------------------

    public function test_active_high_priority_tickets_are_counted(): void
    {
        $tickets = [];
        foreach (TicketStatus::active() as $status) {
            $tickets[] = Ticket::factory()->create(['status' => $status, 'priority' => 3]);
        }
        $tickets[] = Ticket::factory()->create(['status' => TicketStatus::OPEN, 'priority' => 5]);

        $result = $this->check(NightAuditCheckType::OPEN_HIGH_PRIORITY_TICKETS);
        $this->assertSame(5, $result['issue_count']);
        $this->assertSame(array_map(fn ($t) => ['ticket_uuid' => $t->uuid], $tickets), $result['evidence']);
    }

    public function test_normal_priority_and_finished_tickets_are_excluded(): void
    {
        Ticket::factory()->create(['status' => TicketStatus::OPEN, 'priority' => 2]);
        Ticket::factory()->resolved()->create(['priority' => 3]);
        Ticket::factory()->closed()->create(['priority' => 4]);

        $this->assertSame(0, $this->check(NightAuditCheckType::OPEN_HIGH_PRIORITY_TICKETS)['issue_count']);
    }

    // ---- open folio disputes ------------------------------------------------

    public function test_open_dispute_on_old_settled_folio_is_counted(): void
    {
        $old   = $this->reservation(ReservationStatus::CHECKED_OUT, '2026-01-01', '2026-01-03');
        $folio = $this->folio($old, FolioStatus::SETTLED);
        $item  = FolioItem::factory()->create(['folio_id' => $folio->id]);
        $dispute = FolioItemDispute::factory()->create(['folio_item_id' => $item->id]);

        $result = $this->check(NightAuditCheckType::OPEN_FOLIO_DISPUTES);
        $this->assertSame(1, $result['issue_count']);
        $this->assertSame([['dispute_uuid' => $dispute->uuid, 'folio_uuid' => $folio->uuid]], $result['evidence']);
    }

    public function test_resolved_and_rejected_disputes_are_excluded(): void
    {
        FolioItemDispute::factory()->resolved()->create();
        FolioItemDispute::factory()->rejected()->create();

        $this->assertSame(0, $this->check(NightAuditCheckType::OPEN_FOLIO_DISPUTES)['issue_count']);
        $this->assertSame(2, FolioItemDispute::query()->where('status', '!=', FolioDisputeStatus::OPEN)->count());
    }

    // ---- privacy, read-only, budget -----------------------------------------

    public function test_evidence_carries_no_guest_pii(): void
    {
        $guest = Guest::factory()->create([
            'first_name' => 'Zebulon', 'last_name' => 'Quixotic', 'name' => 'Zebulon Quixotic',
            'email' => 'zebulon@example.test', 'phone' => '+963911223344',
        ]);
        $r = $this->reservation(ReservationStatus::CHECKED_IN, '2026-10-07', self::D, [
            'guest_id' => $guest->id, 'last_name' => 'Quixotic', 'phone' => '+963911223344',
        ]);
        $folio = $this->folio($r);
        $item  = FolioItem::factory()->create(['folio_id' => $folio->id]);
        FolioItemDispute::factory()->create(['folio_item_id' => $item->id, 'guest_id' => $guest->id, 'reason' => 'SECRET-DISPUTE-REASON']);
        Ticket::factory()->create(['guest_id' => $guest->id, 'priority' => 3, 'description' => 'SECRET-COMPLAINT', 'subject' => 'SECRET-SUBJECT']);

        $json = json_encode($this->evaluate());

        foreach (['Zebulon', 'Quixotic', 'zebulon@example.test', '963911223344', 'SECRET-DISPUTE-REASON', 'SECRET-COMPLAINT', 'SECRET-SUBJECT'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_evaluation_is_read_only(): void
    {
        $this->seedEveryCategory(3);

        $statements = $this->domainQueries(fn () => $this->evaluate());

        foreach ($statements as $sql) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $sql, $sql);
        }
    }

    public function test_query_count_is_constant_and_bounded(): void
    {
        $empty = $this->countDomainQueries(fn () => $this->evaluate());

        $this->seedEveryCategory(25);
        $full = $this->countDomainQueries(fn () => $this->evaluate());

        $this->assertSame($empty, $full);
        // 5 categories × (1 COUNT + 1 LIMIT-20 sample) = 10 (D-22).
        $this->assertSame(10, $full);

        foreach ($this->evaluate() as $type => $entry) {
            $this->assertSame(25, $entry['issue_count'], $type);
            $this->assertCount(20, $entry['evidence'], $type);
            $this->assertTrue($entry['evidence_truncated'], $type);
        }
    }

    private function seedEveryCategory(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->reservation(ReservationStatus::CHECKED_IN);
            $this->arrival();
            Room::factory()->create(['status' => 'dirty']);
            Ticket::factory()->create(['priority' => 3]);
            FolioItemDispute::factory()->create();
        }
    }
}
