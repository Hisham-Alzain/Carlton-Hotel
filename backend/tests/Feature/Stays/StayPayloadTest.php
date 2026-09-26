<?php

namespace Tests\Feature\Stays;

use App\Enums\BedType;
use App\Enums\CheckInApprovalStatus;
use App\Enums\ReservationStatus;
use App\Http\Resources\Booking\UpcomingStayResource;
use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Booking\StayService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Phase 4 stay blocks on the guest stay reads (GUEST-05 exposure, GUEST-02
 * checklist; D-05, D-11, D-12).
 */
class StayPayloadTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'ABCD-EFGH-JKLM';

    private const ITEM_KEYS = [
        'documents_uploaded', 'check_in_approved', 'preferences_set',
        'arrival_time_set', 'room_assigned', 'digital_key_issued',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function stayFor(Guest $guest, string $state = 'confirmed', array $attributes = [], ?string $roomNumber = null): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->{$state}()->create(array_merge([
            'guest_id'  => $guest->id,
            'check_in'  => '2027-03-12',
            'check_out' => '2027-03-14',
        ], $attributes));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $roomNumber === null ? null : Room::factory()->create([
                'room_type_id' => $type->id,
                'number'       => $roomNumber,
            ])->id,
        ]);
        return $reservation;
    }

    private function giveKey(Reservation $reservation, array $overrides = []): void
    {
        $reservation->forceFill(array_merge([
            'digital_key_code'       => self::CODE,
            'digital_key_hash'       => str_repeat('a1', 32),
            'digital_key_issued_at'  => now(),
            'digital_key_expires_at' => now()->addDays(2),
        ], $overrides))->save();
    }

    private function guestGet(Guest $guest, string $url)
    {
        return $this->withToken($guest->createToken('t')->plainTextToken)->getJson($url);
    }

    public function test_upcoming_stay_carries_the_three_new_blocks(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stayFor($guest);

        $item = $this->guestGet($guest, '/api/stays/upcoming')->assertOk()->json('data.0');

        $this->assertSame(['arrival_time' => null, 'submitted_at' => null, 'approval_status' => null], $item['online_check_in']);
        $this->assertNull($item['digital_key']);
        $this->assertSame($stay->uuid, $item['pre_arrival_checklist']['reservation_uuid']);
        $this->assertFalse($item['pre_arrival_checklist']['complete']);
        $this->assertSame(self::ITEM_KEYS, array_column($item['pre_arrival_checklist']['items'], 'key'));
        $this->assertSame(array_fill(0, 6, false), array_column($item['pre_arrival_checklist']['items'], 'done'));
    }

    public function test_checklist_turns_complete(): void
    {
        $guest = Guest::factory()->create(['bed_type' => BedType::KING]);
        $stay  = $this->stayFor($guest, 'confirmed', ['arrival_time' => '18:30'], '812');
        GuestDocument::factory()->count(2)->create(['guest_id' => $guest->id, 'reservation_id' => $stay->id]);
        CheckInApproval::factory()->create(['reservation_id' => $stay->id, 'status' => CheckInApprovalStatus::APPROVED]);
        $this->giveKey($stay);

        $checklist = $this->guestGet($guest, '/api/stays/upcoming')->assertOk()->json('data.0.pre_arrival_checklist');
        $items     = collect($checklist['items'])->keyBy('key');

        $this->assertTrue($checklist['complete']);
        $this->assertSame(array_fill(0, 6, true), array_column($checklist['items'], 'done'));
        $this->assertSame(2, $items['documents_uploaded']['count']);
        $this->assertSame('approved', $items['check_in_approved']['status']);
        $this->assertSame('18:30', $items['arrival_time_set']['arrival_time']);
        $this->assertSame('812', $items['room_assigned']['room_number']);
        $this->assertSame(now()->addDays(2)->toIso8601String(), $items['digital_key_issued']['expires_at']);
    }

    public function test_active_key_shape_on_all_three_endpoints(): void
    {
        $guest = Guest::factory()->create();
        $this->giveKey($this->stayFor($guest));
        $this->giveKey($this->stayFor($guest, 'checkedIn', ['check_in' => '2027-03-09', 'check_out' => '2027-03-11'], '301'));

        $upcoming = $this->guestGet($guest, '/api/stays/upcoming')->assertOk()->json('data.0.digital_key');
        $active   = $this->guestGet($guest, '/api/stays/active')->assertOk()->json('data.digital_key');
        $status   = $this->guestGet($guest, '/api/stays/status')->assertOk()->json('data.reservation.digital_key');

        foreach ([$upcoming, $active, $status] as $key) {
            $this->assertSame(['code', 'issued_at', 'expires_at'], array_keys($key));
            $this->assertSame(self::CODE, $key['code']);
            $this->assertSame('2027-03-10T09:00:00+00:00', $key['issued_at']);
        }
    }

    public function test_key_is_hidden_when_revoked_expired_or_never_issued(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stayFor($guest);

        $this->assertNull($this->guestGet($guest, '/api/stays/upcoming')->json('data.0.digital_key'));

        $this->giveKey($stay, ['digital_key_revoked_at' => now()->subMinute()]);
        $this->assertNull($this->guestGet($guest, '/api/stays/upcoming')->json('data.0.digital_key'));

        $this->giveKey($stay, ['digital_key_revoked_at' => null, 'digital_key_expires_at' => now()->subMinute()]);
        $body = $this->guestGet($guest, '/api/stays/upcoming')->assertOk();
        $this->assertNull($body->json('data.0.digital_key'));
        $this->assertStringNotContainsString(self::CODE, $body->getContent());
    }

    public function test_undecryptable_code_reads_as_absent(): void
    {
        $guest = Guest::factory()->create();
        $this->giveKey($this->stayFor($guest));
        DB::table('reservations')->update(['digital_key_code' => 'not-a-valid-payload']);

        $this->guestGet($guest, '/api/stays/upcoming')
            ->assertOk()
            ->assertJsonPath('data.0.digital_key', null)
            ->assertJsonPath('data.0.pre_arrival_checklist.reservation_uuid', Reservation::first()->uuid);
    }

    public function test_stay_responses_are_not_cacheable(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'checkedIn', ['check_in' => '2027-03-09', 'check_out' => '2027-03-11']);

        foreach (['/api/stays/status', '/api/stays/active', '/api/stays/upcoming'] as $url) {
            $res = $this->guestGet($guest, $url)->assertOk();
            $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'), $url);
        }
    }

    public function test_past_stays_are_unchanged(): void
    {
        $guest = Guest::factory()->create();
        $this->stayFor($guest, 'checkedOut', ['check_in' => '2027-03-01', 'check_out' => '2027-03-03']);

        $item = $this->guestGet($guest, '/api/stays/past')->assertOk()->json('data.items.0');

        $this->assertNotNull($item);
        foreach (['online_check_in', 'digital_key', 'pre_arrival_checklist'] as $key) {
            $this->assertArrayNotHasKey($key, $item);
        }
    }

    /** Queries for StayService::upcoming + resource resolution with $n furnished stays. */
    private function upcomingQueryCount(Guest $guest): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(StayService::class)->upcoming($guest)['data'];
        UpcomingStayResource::collection($data)->resolve(request());
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private int $roomSeq = 900;

    private function furnishedStay(Guest $guest, int $i): void
    {
        $stay = $this->stayFor($guest, 'confirmed', ['check_in' => "2027-03-1{$i}", 'check_out' => '2027-03-20'], (string) ++$this->roomSeq);
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);
        GuestDocument::factory()->create(['guest_id' => $guest->id, 'reservation_id' => $stay->id]);
    }

    public function test_upcoming_has_no_n_plus_one(): void
    {
        $one = Guest::factory()->create();
        $this->furnishedStay($one, 1);

        $three = Guest::factory()->create();
        foreach ([1, 2, 3] as $i) {
            $this->furnishedStay($three, $i);
        }

        $this->assertSame($this->upcomingQueryCount($one), $this->upcomingQueryCount($three));
    }

    public function test_reservation_serialisation_hides_the_key(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stayFor($guest);
        $this->giveKey($stay);
        $stay->refresh();

        foreach ([json_encode($stay->toArray()), json_encode($stay)] as $serialised) {
            $this->assertStringNotContainsString('digital_key_code', $serialised);
            $this->assertStringNotContainsString('digital_key_hash', $serialised);
            $this->assertStringNotContainsString(self::CODE, $serialised);
        }

        $this->assertEmpty(array_filter((new Reservation)->getFillable(), fn ($f) => str_starts_with($f, 'digital_key')));

        $raw = DB::table('reservations')->where('id', $stay->id)->value('digital_key_code');
        $this->assertNotSame(self::CODE, $raw);
        $this->assertSame(self::CODE, Crypt::decryptString($raw));
    }

    public function test_activity_log_never_contains_the_key(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stayFor($guest);
        $this->giveKey($stay);
        $stay->update(['status' => ReservationStatus::CHECKED_IN]);

        foreach (DB::table('activity_log')->get() as $row) {
            $blob = $row->attribute_changes . $row->properties;
            $this->assertStringNotContainsString(self::CODE, $blob);
            $this->assertStringNotContainsString('digital_key', $blob);
        }
    }

    public function test_approval_status_is_reported(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stayFor($guest);
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);

        $this->guestGet($guest, '/api/stays/upcoming')
            ->assertOk()
            ->assertJsonPath('data.0.online_check_in.approval_status', 'pending');
    }
}
