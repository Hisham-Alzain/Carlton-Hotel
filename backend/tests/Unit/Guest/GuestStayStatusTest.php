<?php

namespace Tests\Unit\Guest;

use App\Enums\GuestStayStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Support\GuestEntitlement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * GuestEntitlement's Phase 4 additions (D-02, D-03): stayStatus precedence,
 * targetFrom / targetReservation, isLive ≡ constrainLive, and the frozen
 * currentReservation (FA-06-1).
 */
class GuestStayStatusTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $t;

    private int $nextId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->t = CarbonImmutable::parse('2027-03-10');
    }

    /** An in-memory reservation (no query) with an explicit id. */
    private function r(string $status, string $in, string $out, ?int $id = null): Reservation
    {
        $reservation = (new Reservation)->forceFill([
            'status' => $status, 'check_in' => $in, 'check_out' => $out,
        ]);
        $reservation->id = $id ?? $this->nextId++;

        return $reservation;
    }

    private function rowStatus(array $live, bool $hasAny = true): GuestStayStatus
    {
        return GuestEntitlement::stayStatus(collect($live), $hasAny, $this->t);
    }

    public function test_precedence(): void
    {
        $departing = $this->r('checked_in', '2027-03-07', '2027-03-10');
        $inHouse   = $this->r('checked_in', '2027-03-08', '2027-03-12');
        $arriving  = $this->r('confirmed', '2027-03-10', '2027-03-12');
        $upcoming  = $this->r('pending', '2027-03-15', '2027-03-17');

        $this->assertSame(GuestStayStatus::DEPARTING, $this->rowStatus([$inHouse, $arriving, $upcoming, $departing]));
        $this->assertSame(GuestStayStatus::IN_HOUSE, $this->rowStatus([$arriving, $upcoming, $inHouse]));
        $this->assertSame(GuestStayStatus::ARRIVING, $this->rowStatus([$upcoming, $arriving]));
        $this->assertSame(GuestStayStatus::UPCOMING, $this->rowStatus([$upcoming]));
        $this->assertSame(GuestStayStatus::PAST, $this->rowStatus([], true));
        $this->assertSame(GuestStayStatus::NONE, $this->rowStatus([], false));
    }

    public function test_fallbacks(): void
    {
        // FA-4.07-1: an overdue confirmed arrival reads arriving; a pending one due today reads upcoming.
        $this->assertSame(GuestStayStatus::ARRIVING, $this->rowStatus([$this->r('confirmed', '2027-03-08', '2027-03-12')]));
        $this->assertSame(GuestStayStatus::ARRIVING, $this->rowStatus([$this->r('confirmed', '2027-03-09', '2027-03-10')]));
        $this->assertSame(GuestStayStatus::UPCOMING, $this->rowStatus([$this->r('pending', '2027-03-10', '2027-03-12')]));
    }

    public function test_past_and_none(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'checked_out', 'check_in' => '2027-03-01', 'check_out' => '2027-03-03']);
        Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'cancelled', 'check_in' => '2027-03-20', 'check_out' => '2027-03-22']);
        Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'confirmed', 'check_in' => '2027-03-05', 'check_out' => '2027-03-09']);

        $live = GuestEntitlement::constrainLive($guest->reservations(), $this->t)->get();
        $this->assertCount(0, $live);
        $this->assertSame(GuestStayStatus::PAST, GuestEntitlement::stayStatus($live, true, $this->t));

        $nobody = Guest::factory()->create();
        $this->assertSame(GuestStayStatus::NONE, GuestEntitlement::stayStatus(
            GuestEntitlement::constrainLive($nobody->reservations(), $this->t)->get(), false, $this->t,
        ));
    }

    public function test_target_prefers_the_in_house_stay(): void
    {
        $inHouse = $this->r('checked_in', '2027-03-08', '2027-03-12');
        $future  = $this->r('confirmed', '2027-03-10', '2027-03-11');

        $this->assertSame($inHouse, GuestEntitlement::targetFrom(collect([$future, $inHouse]), $this->t));

        // Even a checked_in stay past its check_out (overstay) is the target.
        $overstay = $this->r('checked_in', '2027-03-05', '2027-03-09');
        $this->assertSame($overstay, GuestEntitlement::targetFrom(collect([$future, $overstay]), $this->t));
    }

    public function test_target_is_the_next_arrival_not_the_latest(): void
    {
        $later  = $this->r('confirmed', '2027-03-20', '2027-03-22');
        $nearer = $this->r('pending', '2027-03-12', '2027-03-14');
        $this->assertSame($nearer, GuestEntitlement::targetFrom(collect([$later, $nearer]), $this->t));

        $low  = $this->r('confirmed', '2027-03-15', '2027-03-17', 10);
        $high = $this->r('confirmed', '2027-03-15', '2027-03-18', 20);
        $this->assertSame($low, GuestEntitlement::targetFrom(collect([$high, $low]), $this->t));

        $today = $this->r('confirmed', '2027-03-10', '2027-03-12');
        $this->assertSame($today, GuestEntitlement::targetFrom(collect([$later, $today]), $this->t));

        $this->assertNull(GuestEntitlement::targetFrom(new Collection, $this->t));
        // An overdue arrival (check_in < T) is live but not a "next arrival".
        $this->assertNull(GuestEntitlement::targetFrom(collect([$this->r('confirmed', '2027-03-08', '2027-03-12')]), $this->t));
    }

    public function test_target_reservation_queries_the_same_answer(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'confirmed', 'check_in' => '2027-03-20', 'check_out' => '2027-03-22']);
        $next = Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'pending', 'check_in' => '2027-03-12', 'check_out' => '2027-03-14']);
        Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'checked_out', 'check_in' => '2027-03-01', 'check_out' => '2027-03-03']);

        $target = GuestEntitlement::targetReservation($guest);
        $from   = GuestEntitlement::targetFrom($guest->reservations()->get(), $this->t);

        $this->assertSame($next->id, $target->id);
        $this->assertSame($target->id, $from->id);

        $this->assertNull(GuestEntitlement::targetReservation(Guest::factory()->create()));
    }

    public function test_is_live_matches_constrain_live(): void
    {
        $guest = Guest::factory()->create();
        foreach (ReservationStatus::cases() as $status) {
            foreach (['2027-03-09', '2027-03-10', '2027-03-11'] as $out) {
                Reservation::factory()->create([
                    'guest_id' => $guest->id, 'status' => $status, 'check_in' => '2027-03-05', 'check_out' => $out,
                ]);
            }
        }

        $php = $guest->reservations()->get()
            ->filter(fn (Reservation $r) => GuestEntitlement::isLive($r, $this->t))
            ->pluck('id')->sort()->values()->all();
        $sql = GuestEntitlement::constrainLive($guest->reservations(), $this->t)->get()
            ->pluck('id')->sort()->values()->all();

        $this->assertNotEmpty($sql);
        $this->assertSame($sql, $php);
    }

    public function test_current_reservation_is_unchanged(): void
    {
        $guest   = Guest::factory()->create();
        $inHouse = Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'checked_in', 'check_in' => '2027-03-08', 'check_out' => '2027-03-12']);
        $future  = Reservation::factory()->create(['guest_id' => $guest->id, 'status' => 'confirmed', 'check_in' => '2027-04-01', 'check_out' => '2027-04-03']);

        // FA-06-1: currentReservation() still returns the latest booking.
        $this->assertSame($future->id, GuestEntitlement::currentReservation($guest)->id);
        $this->assertSame($inHouse->id, GuestEntitlement::targetReservation($guest)->id);
    }
}
