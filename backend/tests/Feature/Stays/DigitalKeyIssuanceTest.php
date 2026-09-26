<?php

namespace Tests\Feature\Stays;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\CheckInApprovalStatus;
use App\Events\CheckInApproved;
use App\Listeners\SendCheckInApprovedNotification;
use App\Models\CheckInApproval;
use App\Models\DeviceToken;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Digital key issuance on check-in approval, revocation on rejection, the
 * "key ready" push (Phase 4, GUEST-05, D-11, D-14).
 */
class DigitalKeyIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_PATTERN = '/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}$/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus', 'hotel.check_out_time' => '12:00']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService;
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    /** A guest's stay 2027-03-11..2027-03-12 with one room line and a pending approval. */
    private function stay(string $status = 'confirmed', array $attributes = []): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->create(array_merge([
            'status'    => $status,
            'check_in'  => '2027-03-11',
            'check_out' => '2027-03-12',
        ], $attributes));
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $type->id]);
        CheckInApproval::factory()->create(['reservation_id' => $reservation->id]);

        return $reservation;
    }

    private function decide(Reservation $reservation, string $status, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('reservations.create'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => $status]);
    }

    private function guestGet(Reservation $reservation, string $url)
    {
        return $this->withToken(Guest::findOrFail($reservation->guest_id)->createToken('t')->plainTextToken)->getJson($url);
    }

    private function code(Reservation $reservation): ?string
    {
        return Reservation::findOrFail($reservation->id)->digital_key_code;
    }

    public function test_approval_issues_a_key_the_guest_can_read(): void
    {
        $stay = $this->stay();

        $this->decide($stay, 'approved')->assertOk()->assertJsonPath('message', 'Check-in approved.');

        $item = $this->guestGet($stay, '/api/stays/upcoming')->assertOk()->json('data.0');
        $this->assertMatchesRegularExpression(self::KEY_PATTERN, $item['digital_key']['code']);
        $this->assertSame('2027-03-10T09:00:00+00:00', $item['digital_key']['issued_at']);
        $this->assertSame('2027-03-12T09:00:00+00:00', $item['digital_key']['expires_at']);
        $this->assertTrue(collect($item['pre_arrival_checklist']['items'])->firstWhere('key', 'digital_key_issued')['done']);
    }

    public function test_code_is_encrypted_and_hashed_at_rest(): void
    {
        $stay = $this->stay();
        $this->decide($stay, 'approved')->assertOk();

        $code = $this->code($stay);
        $raw  = DB::table('reservations')->where('id', $stay->id)->value('digital_key_code');

        $this->assertNotSame($code, $raw);
        $this->assertSame($code, Crypt::decryptString($raw));
        $this->assertSame(
            hash_hmac('sha256', $code, config('app.key')),
            DB::table('reservations')->where('id', $stay->id)->value('digital_key_hash'),
        );
    }

    public function test_expiry_follows_the_hotel_timezone_and_check_out_time(): void
    {
        config(['hotel.timezone' => 'Asia/Tokyo', 'hotel.check_out_time' => '11:00']);
        $stay = $this->stay();

        $this->decide($stay, 'approved')->assertOk();

        $this->guestGet($stay, '/api/stays/upcoming')
            ->assertJsonPath('data.0.digital_key.expires_at', '2027-03-12T02:00:00+00:00');
    }

    public function test_approving_a_checked_in_stay_issues_a_key(): void
    {
        $stay = $this->stay('checked_in', ['check_in' => '2027-03-09']);

        $this->decide($stay, 'approved')->assertOk();

        $key = $this->guestGet($stay, '/api/stays/active')->assertOk()->json('data.digital_key');
        $this->assertMatchesRegularExpression(self::KEY_PATTERN, $key['code']);
    }

    public static function nonHolding(): array
    {
        return [
            'cancelled'            => ['cancelled'],
            'checked_out'          => ['checked_out'],
            'pending'              => ['pending'],
            'pending_verification' => ['pending_verification'],
        ];
    }

    #[DataProvider('nonHolding')]
    public function test_non_holding_states_get_no_key(string $status): void
    {
        $stay = $this->stay($status);

        $this->decide($stay, 'approved')->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertNull($stay->refresh()->digital_key_issued_at);
        $this->assertNull(DB::table('reservations')->where('id', $stay->id)->value('digital_key_code'));
    }

    public function test_reapproval_keeps_the_active_key(): void
    {
        $stay = $this->stay();
        $this->decide($stay, 'approved')->assertOk();
        $code   = $this->code($stay);
        $issued = $stay->refresh()->digital_key_issued_at;

        $this->travel(1)->hours();
        $this->decide($stay, 'approved')->assertOk();

        $this->assertSame($code, $this->code($stay));
        $this->assertTrue($issued->equalTo($stay->refresh()->digital_key_issued_at));
    }

    public function test_reject_revokes_and_a_later_approval_mints_a_new_code(): void
    {
        $stay = $this->stay();
        $this->decide($stay, 'approved')->assertOk();
        $first = $this->code($stay);

        $this->decide($stay, 'rejected')->assertOk();
        $stay->refresh();
        $this->assertNotNull($stay->digital_key_revoked_at);
        $this->assertSame('rejected', $stay->digital_key_revoked_reason);
        $this->assertNull($stay->digital_key_code);
        $this->assertNull($stay->digital_key_hash);
        $this->guestGet($stay, '/api/stays/upcoming')->assertJsonPath('data.0.digital_key', null);

        $this->decide($stay, 'approved')->assertOk();
        $second = $this->code($stay);
        $this->assertMatchesRegularExpression(self::KEY_PATTERN, $second);
        $this->assertNotSame($first, $second);
        $this->assertNull($stay->refresh()->digital_key_revoked_at);
        $this->assertNull($stay->digital_key_revoked_reason);
    }

    public function test_rejecting_without_a_key_changes_nothing(): void
    {
        $stay = $this->stay();

        $this->decide($stay, 'rejected')->assertOk();

        $stay->refresh();
        foreach (['digital_key_issued_at', 'digital_key_revoked_at', 'digital_key_revoked_reason', 'digital_key_hash'] as $column) {
            $this->assertNull($stay->{$column}, $column);
        }
    }

    public function test_online_check_in_is_not_required(): void
    {
        $stay = $this->stay();
        $this->assertNull($stay->arrival_time);

        $this->decide($stay, 'approved')->assertOk();

        $this->assertMatchesRegularExpression(self::KEY_PATTERN, $this->code($stay));
    }

    public function test_approval_push_never_carries_the_code(): void
    {
        $fake = $this->fakeFirebase();
        $stay = $this->stay();
        DeviceToken::factory()->create(['guest_id' => $stay->guest_id]);

        $this->decide($stay, 'approved')->assertOk();
        $code = $this->code($stay);

        $rows = DB::table('guest_notifications')->where('guest_id', $stay->guest_id)->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('check_in_approved', $row->type);
        $this->assertSame('Your check-in is approved', $row->title);
        $this->assertSame('Your digital key is ready in the app.', $row->body);
        $this->assertSame(['reservation_uuid' => $stay->uuid], json_decode($row->data, true));

        $this->assertCount(1, $fake->pushes);
        $push = $fake->pushes[0];
        foreach ([$push['title'], $push['body'], json_encode($push['data']), $row->title, $row->body, (string) $row->data] as $text) {
            $this->assertStringNotContainsString($code, $text);
        }
    }

    public function test_check_in_approved_dispatch_rules(): void
    {
        Event::fake([CheckInApproved::class]);
        $stay     = $this->stay();
        $approval = CheckInApproval::where('reservation_id', $stay->id)->firstOrFail();

        $this->decide($stay, 'approved')->assertOk();
        Event::assertDispatchedTimes(CheckInApproved::class, 1);
        Event::assertDispatched(CheckInApproved::class, fn (CheckInApproved $e) => $e->reservation->uuid === $stay->uuid
            && $e->approval->uuid === $approval->uuid);

        $this->decide($stay, 'approved')->assertOk();
        Event::assertDispatchedTimes(CheckInApproved::class, 1);

        $cancelled = $this->stay('cancelled');
        $this->decide($cancelled, 'approved')->assertOk();
        Event::assertDispatchedTimes(CheckInApproved::class, 1);

        Event::assertListening(CheckInApproved::class, SendCheckInApprovedNotification::class);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new CheckInApproved($stay, $approval));
    }

    public function test_approve_response_never_contains_the_code(): void
    {
        $stay = $this->stay();

        $body = $this->decide($stay, 'approved')->assertOk()->getContent();

        $this->assertStringNotContainsString($this->code($stay), $body);
        $this->assertStringNotContainsString('digital_key', $body);
    }

    public function test_changing_check_out_moves_the_expiry(): void
    {
        $stay = $this->stay();
        $this->decide($stay, 'approved')->assertOk();

        $stay->refresh()->update(['check_out' => '2027-03-14']);
        $this->assertSame('2027-03-14T09:00:00+00:00', $stay->refresh()->digital_key_expires_at->toIso8601String());

        $revoked = $this->stay();
        $this->decide($revoked, 'approved')->assertOk();
        $this->decide($revoked, 'rejected')->assertOk();
        $before = $revoked->refresh()->digital_key_expires_at->toIso8601String();

        $revoked->update(['check_out' => '2027-03-20']);
        $this->assertSame($before, $revoked->refresh()->digital_key_expires_at->toIso8601String());
    }
}
