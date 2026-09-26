<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\CheckOutReservationAction;
use App\Actions\Folio\GenerateFolioAction;
use App\Actions\Folio\SettleFolioAction;
use App\Enums\CheckOutMode;
use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\FolioUnsettledException;
use App\Exceptions\ReservationStateException;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CheckOutReservationAction without HTTP (Phase 3, D-06..D-09).
 *
 * Settle / check-out ordering is proven in its sequential form (stale models,
 * preloaded relations). Both actions lock the same folio row, which serialises
 * true parallel callers on MySQL; SQLite cannot run them concurrently.
 */
class CheckOutReservationActionTest extends TestCase
{
    use RefreshDatabase;

    private CheckOutReservationAction $action;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->action = app(CheckOutReservationAction::class);
        $this->staff  = User::factory()->create();
        $this->staff->assignRole('reception');
    }

    private function checkedInStay(?string $folioStatus = 'settled'): Reservation
    {
        $type        = RoomType::factory()->create();
        $room        = Room::factory()->create(['room_type_id' => $type->id, 'status' => 'available']);
        $reservation = Reservation::factory()->checkedIn()->create([
            'check_in'  => '2027-03-10',
            'check_out' => '2027-03-12',
            'total_usd' => 300,
        ]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room->id,
        ]);

        if ($folioStatus !== null) {
            $folio = Folio::factory()->create([
                'reservation_id' => $reservation->id,
                'status'         => $folioStatus,
                'subtotal_usd'   => 300,
                'total_usd'      => 300,
                'settled_at'     => $folioStatus === 'settled' ? now()->subHour() : null,
            ]);
            FolioItem::factory()->create(['folio_id' => $folio->id, 'amount_usd' => 300, 'source_id' => $reservation->id]);
        }

        return $reservation;
    }

    public function test_returns_data_and_code(): void
    {
        $reservation = $this->checkedInStay('settled');

        $result = $this->action->handle($reservation, CheckOutMode::NONE, $this->staff);

        $this->assertSame(['data', 'code'], array_keys($result));
        $this->assertSame(200, $result['code']);
        $this->assertSame(ReservationStatus::CHECKED_OUT, $result['data']->status);
        foreach (['rooms', 'guest', 'folio'] as $relation) {
            $this->assertTrue($result['data']->relationLoaded($relation), $relation);
        }
        $this->assertTrue($result['data']->rooms->first()->relationLoaded('room'));
    }

    public function test_none_mode_open_folio_throws_with_context(): void
    {
        $reservation = $this->checkedInStay('open');
        $folio       = $reservation->folio;

        try {
            $this->action->handle($reservation, CheckOutMode::NONE, $this->staff);
            $this->fail('FolioUnsettledException expected');
        } catch (FolioUnsettledException $e) {
            $this->assertSame(['folio_uuid', 'total_usd', 'can_force'], array_keys($e->context()));
            $this->assertSame($folio->uuid, $e->context()['folio_uuid']);
            $this->assertSame('300.00', $e->context()['total_usd']);
            $this->assertTrue($e->context()['can_force']);
        }

        // A null actor (system) can never force.
        try {
            $this->action->handle($reservation, CheckOutMode::NONE, null);
            $this->fail('FolioUnsettledException expected');
        } catch (FolioUnsettledException $e) {
            $this->assertFalse($e->context()['can_force']);
        }

        $this->assertSame(ReservationStatus::CHECKED_IN, $reservation->fresh()->status);
    }

    public function test_guest_express_mode_accepts_an_open_folio_with_a_null_actor(): void
    {
        $reservation = $this->checkedInStay('open');
        $room        = $reservation->rooms()->first()->room;

        $result = $this->action->handle($reservation, CheckOutMode::GUEST_EXPRESS, null);

        $this->assertSame(ReservationStatus::CHECKED_OUT, $result['data']->status);
        $this->assertSame(FolioStatus::OPEN, $reservation->folio()->first()->status);
        $this->assertSame('dirty', $room->fresh()->status->value);

        $history = DB::table('room_status_history')->where('room_id', $room->id)->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->changed_by);

        $marker = DB::table('activity_log')->where('description', 'reservation.check_out_guest_express')->get();
        $this->assertCount(1, $marker);
        $this->assertNull($marker[0]->causer_id);
        $this->assertNull($marker[0]->causer_type);
    }

    public function test_a_stale_model_cannot_check_out_twice(): void
    {
        $reservation = $this->checkedInStay('settled');
        $stale       = Reservation::find($reservation->id);

        $this->action->handle(Reservation::find($reservation->id), CheckOutMode::NONE, $this->staff);
        $stamp = $reservation->fresh()->checked_out_at;

        $this->assertSame(ReservationStatus::CHECKED_IN, $stale->status);
        try {
            $this->action->handle($stale, CheckOutMode::NONE, $this->staff);
            $this->fail('ReservationStateException expected');
        } catch (ReservationStateException $e) {
            $this->assertSame(['status' => 'checked_out', 'allowed' => ['checked_in']], $e->context());
        }

        $this->assertEquals($stamp, $reservation->fresh()->checked_out_at);
        $this->assertSame(1, DB::table('room_status_history')->count());
    }

    public function test_settle_then_check_out_needs_no_force(): void
    {
        $reservation = $this->checkedInStay('open');
        $stale       = Reservation::with('folio')->find($reservation->id);
        $this->assertSame(FolioStatus::OPEN, $stale->folio->status);

        app(SettleFolioAction::class)->handle(Folio::where('reservation_id', $reservation->id)->first(), 'cash', 300, $this->staff);

        // The stale relation still says open; the action re-reads the folio under its lock.
        $result = $this->action->handle($stale, CheckOutMode::NONE, $this->staff);

        $this->assertSame(ReservationStatus::CHECKED_OUT, $result['data']->status);
        $this->assertSame(FolioStatus::SETTLED, $result['data']->folio->status);
        $this->assertSame(0, DB::table('activity_log')->where('description', 'reservation.check_out_forced')->count());
    }

    public function test_a_preloaded_settled_relation_does_not_bypass_the_gate(): void
    {
        $reservation = $this->checkedInStay('open');
        $copy        = $reservation->folio()->first()->replicate();
        $copy->status     = FolioStatus::SETTLED;
        $copy->settled_at = now();
        $reservation->setRelation('folio', $copy);

        $this->expectException(FolioUnsettledException::class);
        try {
            $this->action->handle($reservation, CheckOutMode::NONE, $this->staff);
        } finally {
            $this->assertSame(ReservationStatus::CHECKED_IN, $reservation->fresh()->status);
        }
    }

    public function test_forced_check_out_then_settle_still_works(): void
    {
        $reservation = $this->checkedInStay('open');

        $this->action->handle($reservation, CheckOutMode::STAFF_FORCE, $this->staff, 'Company pays');
        $folio   = Folio::where('reservation_id', $reservation->id)->first();
        $itemIds = $folio->items()->orderBy('id')->pluck('id')->all();
        $this->assertSame(FolioStatus::OPEN, $folio->status);

        $settled = app(SettleFolioAction::class)->handle($folio, 'cash', 300, $this->staff)['data'];
        $this->assertSame(FolioStatus::SETTLED, $settled->status);
        $this->assertCount(1, $settled->payments);

        $again = app(GenerateFolioAction::class)->handle($reservation->fresh())['data'];
        $this->assertSame($itemIds, $again->items->sortBy('id')->pluck('id')->values()->all());
        $this->assertSame(1, DB::table('activity_log')->where('description', 'reservation.check_out_forced')->count());
    }

    public function test_legacy_checked_out_stay_without_a_folio_still_gets_one(): void
    {
        // FA-05-3: the checked-out guard must not stop a first build.
        $reservation = $this->checkedInStay(null);
        $reservation->update(['status' => ReservationStatus::CHECKED_OUT]);

        $folio = app(GenerateFolioAction::class)->handle($reservation->fresh())['data'];

        $this->assertSame('300.00', (string) $folio->total_usd);
        $this->assertCount(1, $folio->items);
    }
}
