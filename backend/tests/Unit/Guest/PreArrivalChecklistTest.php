<?php

namespace Tests\Unit\Guest;

use App\Enums\BedType;
use App\Enums\CheckInApprovalStatus;
use App\Enums\FloorPreference;
use App\Enums\PillowType;
use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\PreArrivalChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * App\Support\PreArrivalChecklist (Phase 4, D-05): derived, ordered, query-free.
 */
class PreArrivalChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    /** A confirmed stay; $room: 'none' = no line, 'empty' = line without a room, else the room number. */
    private function stay(array $guestAttrs = [], array $attrs = [], string $room = 'none'): Reservation
    {
        $guest       = Guest::factory()->create($guestAttrs);
        $reservation = Reservation::factory()->confirmed()->create(array_merge([
            'guest_id' => $guest->id, 'check_in' => '2027-03-12', 'check_out' => '2027-03-14',
        ], $attrs));

        if ($room !== 'none') {
            $type = RoomType::factory()->create();
            ReservationRoom::factory()->create([
                'reservation_id' => $reservation->id,
                'room_type_id'   => $type->id,
                'room_id'        => $room === 'empty' ? null : Room::factory()->create(['room_type_id' => $type->id, 'number' => $room])->id,
            ]);
        }

        return $reservation;
    }

    private function loaded(Reservation $reservation): Reservation
    {
        $fresh = Reservation::with(['rooms.room', 'checkInApproval', 'documents'])->findOrFail($reservation->id);
        $fresh->setRelation('guest', Guest::findOrFail($reservation->guest_id));

        return $fresh;
    }

    private function item(array $checklist, string $key): array
    {
        return collect($checklist['items'])->firstWhere('key', $key);
    }

    private function key(Reservation $reservation, array $overrides = []): void
    {
        $reservation->forceFill(array_merge([
            'digital_key_code'       => 'ABCD-EFGH-JKLM',
            'digital_key_issued_at'  => now(),
            'digital_key_expires_at' => now()->addDay(),
        ], $overrides))->save();
    }

    public function test_items_are_in_the_contract_order_with_details(): void
    {
        $checklist = PreArrivalChecklist::for($this->loaded($this->stay()));

        $this->assertSame(['reservation_uuid', 'complete', 'items'], array_keys($checklist));
        $this->assertSame(
            ['documents_uploaded', 'check_in_approved', 'preferences_set', 'arrival_time_set', 'room_assigned', 'digital_key_issued'],
            array_column($checklist['items'], 'key'),
        );
        $this->assertSame(['key', 'done', 'count'], array_keys($this->item($checklist, 'documents_uploaded')));
        $this->assertSame(['key', 'done', 'status'], array_keys($this->item($checklist, 'check_in_approved')));
        $this->assertSame(['key', 'done'], array_keys($this->item($checklist, 'preferences_set')));
        $this->assertSame(['key', 'done', 'arrival_time'], array_keys($this->item($checklist, 'arrival_time_set')));
        $this->assertSame(['key', 'done', 'room_number'], array_keys($this->item($checklist, 'room_assigned')));
        $this->assertSame(['key', 'done', 'expires_at'], array_keys($this->item($checklist, 'digital_key_issued')));
        $this->assertSame(0, $this->item($checklist, 'documents_uploaded')['count']);
        $this->assertNull($this->item($checklist, 'check_in_approved')['status']);
    }

    public function test_preferences_set_rules(): void
    {
        $cases = [
            [[], false],
            [['pillow_type' => PillowType::SOFT], true],
            [['floor_preference' => FloorPreference::ANY], true],
            [['bed_type' => BedType::KING], true],
            [['preferences_other' => 'Near the lift'], false],
        ];

        foreach ($cases as [$attrs, $expected]) {
            $checklist = PreArrivalChecklist::for($this->loaded($this->stay($attrs)));
            $this->assertSame($expected, $this->item($checklist, 'preferences_set')['done'], json_encode($attrs));
        }
    }

    public function test_room_assigned_needs_a_room_on_the_line(): void
    {
        $none  = $this->item(PreArrivalChecklist::for($this->loaded($this->stay())), 'room_assigned');
        $empty = $this->item(PreArrivalChecklist::for($this->loaded($this->stay([], [], 'empty'))), 'room_assigned');
        $room  = $this->item(PreArrivalChecklist::for($this->loaded($this->stay([], [], '812'))), 'room_assigned');

        $this->assertFalse($none['done']);
        $this->assertFalse($empty['done']);
        $this->assertNull($empty['room_number']);
        $this->assertTrue($room['done']);
        $this->assertSame('812', $room['room_number']);
    }

    public function test_digital_key_issued_follows_the_active_rule(): void
    {
        $active = $this->stay();
        $this->key($active);
        $expired = $this->stay();
        $this->key($expired, ['digital_key_expires_at' => now()->subMinute()]);
        $revoked = $this->stay();
        $this->key($revoked, ['digital_key_code' => null, 'digital_key_revoked_at' => now()]);

        $a = $this->item(PreArrivalChecklist::for($this->loaded($active)), 'digital_key_issued');
        $e = $this->item(PreArrivalChecklist::for($this->loaded($expired)), 'digital_key_issued');
        $r = $this->item(PreArrivalChecklist::for($this->loaded($revoked)), 'digital_key_issued');

        $this->assertTrue($a['done']);
        $this->assertSame(now()->addDay()->toIso8601String(), $a['expires_at']);
        $this->assertFalse($e['done']);
        $this->assertNull($e['expires_at']);
        $this->assertFalse($r['done']);
        $this->assertNull($r['expires_at']);
    }

    public function test_complete_only_when_all_six_are_done(): void
    {
        $stay = $this->stay(['bed_type' => BedType::KING], ['arrival_time' => '18:30'], '812');
        GuestDocument::factory()->create(['guest_id' => $stay->guest_id, 'reservation_id' => $stay->id]);
        $approval = CheckInApproval::factory()->create(['reservation_id' => $stay->id, 'status' => CheckInApprovalStatus::APPROVED]);

        // Five of six: no key yet.
        $this->assertFalse(PreArrivalChecklist::for($this->loaded($stay))['complete']);

        $this->key($stay);
        $this->assertTrue(PreArrivalChecklist::for($this->loaded($stay))['complete']);

        $approval->update(['status' => CheckInApprovalStatus::REJECTED]);
        $this->assertFalse(PreArrivalChecklist::for($this->loaded($stay))['complete']);
    }

    public function test_builds_without_queries(): void
    {
        $stay = $this->stay(['bed_type' => BedType::KING], [], '812');
        GuestDocument::factory()->create(['guest_id' => $stay->guest_id, 'reservation_id' => $stay->id]);
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);
        $loaded = $this->loaded($stay);

        DB::flushQueryLog();
        DB::enableQueryLog();
        PreArrivalChecklist::for($loaded);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_missing_relation_fails_loudly(): void
    {
        $stay = Reservation::with(['rooms.room', 'checkInApproval'])->findOrFail($this->stay()->id);
        $stay->setRelation('guest', Guest::first());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('documents');

        PreArrivalChecklist::for($stay);
    }
}
