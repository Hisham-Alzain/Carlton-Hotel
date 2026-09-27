<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\CheckOutMode;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\ServiceRequestPriority;
use App\Events\ReservationCheckedOut;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * D-04 / D-09: every check-out leaves exactly one open turnover task per
 * assigned room, created by the synchronous CreateTurnoverTaskOnCheckOut.
 */
class TurnoverOnCheckOutTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;
    private int $roomNumber = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->type = RoomType::factory()->create();
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    /** A checked_in stay 2027-03-10..2027-03-12; one line per entry (null = no room). */
    private function checkedInStay(?string $folioStatus = 'settled', array $roomStatuses = ['available'], array $attrs = []): Reservation
    {
        $reservation = Reservation::factory()->checkedIn()->create(array_merge([
            'check_in'      => '2027-03-10',
            'check_out'     => '2027-03-12',
            'checked_in_at' => '2027-03-10 12:00:00',
            'total_usd'     => 300,
        ], $attrs));

        foreach ($roomStatuses as $status) {
            $room = $status === null ? null : Room::factory()->create([
                'room_type_id' => $this->type->id,
                'number'       => (string) ++$this->roomNumber,
                'status'       => $status,
            ]);
            ReservationRoom::factory()->create([
                'reservation_id' => $reservation->id,
                'room_type_id'   => $this->type->id,
                'room_id'        => $room?->id,
                'price_usd'      => 300,
            ]);
        }

        if ($folioStatus !== null) {
            $folio = Folio::factory()->create([
                'reservation_id' => $reservation->id,
                'status'         => $folioStatus,
                'subtotal_usd'   => 300,
                'total_usd'      => 300,
                'settled_at'     => $folioStatus === 'settled' ? now()->subHour() : null,
            ]);
            FolioItem::factory()->create([
                'folio_id'    => $folio->id,
                'amount_usd'  => 300,
                'source_type' => 'reservation',
                'source_id'   => $reservation->id,
            ]);
        }

        return $reservation;
    }

    private function checkOut(Reservation $reservation, array $body = [])
    {
        return $this->withToken($this->presetToken('reception'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-out", $body);
    }

    /** @return list<Room> */
    private function roomsOf(Reservation $reservation): array
    {
        return Room::whereIn('id', $reservation->rooms()->whereNotNull('room_id')->pluck('room_id'))->orderBy('id')->get()->all();
    }

    private function openTurnovers(Room $room)
    {
        return HousekeepingTask::open()->where('room_id', $room->id)->where('type', 'turnover')->get();
    }

    public function test_check_out_creates_one_open_turnover_per_assigned_room(): void
    {
        $reservation = $this->checkedInStay('settled', ['available', 'dirty', null]);

        $this->checkOut($reservation)->assertOk();

        $this->assertSame(2, HousekeepingTask::count());

        foreach ($this->roomsOf($reservation) as $room) {
            $tasks = $this->openTurnovers($room);
            $this->assertCount(1, $tasks);

            $task = $tasks[0];
            $this->assertSame(HousekeepingTaskStatus::PENDING, $task->status);
            $this->assertSame(ServiceRequestPriority::NORMAL, $task->priority);
            $this->assertSame($reservation->id, $task->reservation_id);
            $this->assertNull($task->created_by);
            $this->assertSame('2027-03-12 12:00:00', $task->due_at->utc()->format('Y-m-d H:i:s'));

            $history = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->get();
            $this->assertCount(1, $history);
            $this->assertSame('check_out', $history[0]->reason);
        }
    }

    public function test_sla_minutes_come_from_config(): void
    {
        config(['hotel.turnover_sla_minutes' => 45]);
        $reservation = $this->checkedInStay();

        $this->checkOut($reservation)->assertOk();

        $this->assertSame('2027-03-12 10:45:00', HousekeepingTask::first()->due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_arrival_today_makes_the_turnover_high_priority(): void
    {
        $reservation = $this->checkedInStay('settled', ['available', 'available']);
        [$roomA, $roomB] = $this->roomsOf($reservation);

        $arrivingToday = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-12', 'check_out' => '2027-03-14']);
        ReservationRoom::factory()->create(['reservation_id' => $arrivingToday->id, 'room_type_id' => $this->type->id, 'room_id' => $roomA->id]);

        $arrivingTomorrow = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-13', 'check_out' => '2027-03-15']);
        ReservationRoom::factory()->create(['reservation_id' => $arrivingTomorrow->id, 'room_type_id' => $this->type->id, 'room_id' => $roomB->id]);

        $this->checkOut($reservation)->assertOk();

        $this->assertSame(ServiceRequestPriority::HIGH, $this->openTurnovers($roomA)[0]->priority);
        $this->assertSame(ServiceRequestPriority::NORMAL, $this->openTurnovers($roomB)[0]->priority);
    }

    public function test_redispatch_keeps_one_open_turnover(): void
    {
        $reservation = $this->checkedInStay('settled', ['available', 'available']);
        $this->checkOut($reservation)->assertOk();

        event(new ReservationCheckedOut($reservation->fresh(), CheckOutMode::NONE, null));
        event(new ReservationCheckedOut($reservation->fresh(), CheckOutMode::NONE, null));

        foreach ($this->roomsOf($reservation) as $room) {
            $tasks = $this->openTurnovers($room);
            $this->assertCount(1, $tasks);
            $this->assertSame(1, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $tasks[0]->id)->count());
        }
        $this->assertSame(2, HousekeepingTask::count());
    }

    public function test_guest_express_checkout_creates_the_turnover(): void
    {
        $guest       = Guest::factory()->create();
        $reservation = $this->checkedInStay(null, ['available'], ['guest_id' => $guest->id]);
        $room        = $this->roomsOf($reservation)[0];

        $this->withToken($guest->createToken('guest')->plainTextToken)
            ->postJson('/api/folio/approve')
            ->assertOk();

        $tasks = $this->openTurnovers($room);
        $this->assertCount(1, $tasks);
        $this->assertNull($tasks[0]->created_by);
        $this->assertSame($reservation->id, $tasks[0]->reservation_id);
    }

    public function test_refused_check_out_creates_no_task(): void
    {
        $reservation = $this->checkedInStay('open');

        $this->checkOut($reservation)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'folio_unsettled');

        $this->assertSame(0, HousekeepingTask::count());
    }

    public function test_a_failing_room_does_not_fail_the_check_out(): void
    {
        Exceptions::fake();

        $reservation     = $this->checkedInStay('settled', ['available', 'available']);
        [$roomA, $roomB] = $this->roomsOf($reservation);

        // A closed row written around the model still holds A's key (stale).
        DB::table('housekeeping_tasks')->insert([
            'uuid'       => (string) Str::uuid(),
            'room_id'    => $roomA->id,
            'type'       => 'turnover',
            'status'     => 'done',
            'priority'   => 'normal',
            'dedupe_key' => "{$roomA->id}:turnover",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->checkOut($reservation)->assertOk()->assertJsonPath('data.status', 'checked_out');

        $this->assertCount(0, $this->openTurnovers($roomA));
        $this->assertCount(1, $this->openTurnovers($roomB));
        Exceptions::assertReported(UniqueConstraintViolationException::class);
    }
}
