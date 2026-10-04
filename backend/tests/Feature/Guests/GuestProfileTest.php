<?php

namespace Tests\Feature\Guests;

use App\Enums\BedType;
use App\Enums\CheckInApprovalStatus;
use App\Enums\FloorPreference;
use App\Enums\PillowType;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Resources\Guest\GuestProfileResource;
use App\Models\CheckInApproval;
use App\Models\Guest;
use App\Models\GuestDocument;
use App\Models\GuestNote;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Guest\GuestService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * GET /api/guests/{guest} — the staff "guest at the counter" profile
 * (Phase 4, GUEST-02, D-03, D-04, D-05).
 */
class GuestProfileTest extends TestCase
{
    use RefreshDatabase;

    private const TOP_KEYS = [
        'uuid', 'name', 'first_name', 'last_name', 'phone', 'phone_country', 'phone_verified', 'email',
        'email_verified', 'preferred_locale', 'created_at', 'stay_status', 'stats', 'preferences',
        'current_reservation', 'pre_arrival_checklist', 'stay_history', 'stays_total', 'has_more',
        'notes', 'notes_count',
        // Phase 9.1 (D-12), additive.
        'account_status', 'account_deleted_at',
    ];

    private const RESERVATION_KEYS = [
        'uuid', 'booking_code', 'status', 'check_in', 'check_out', 'checked_in_at', 'arrival_time',
        'online_check_in_submitted_at', 'room', 'room_type', 'check_in_approval', 'documents', 'digital_key',
    ];

    private int $roomSeq = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus', 'hotel.check_out_time' => '12:00']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00')); // T = 2027-03-10
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function stay(Guest $g, string $state, string $in, string $out, bool $withRoom = true): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->create([
            'guest_id' => $g->id, 'status' => $state, 'check_in' => $in, 'check_out' => $out,
        ]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $withRoom
                ? Room::factory()->create(['room_type_id' => $type->id, 'number' => (string) ++$this->roomSeq])->id
                : null,
        ]);
        return $reservation;
    }

    private function profile(Guest $guest, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('guests.view'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/guests/{$guest->uuid}");
    }

    /** Approve through the real endpoint so the key is minted by the production path. */
    private function decide(Reservation $reservation, string $status, ?User $staff = null): void
    {
        $staff ??= tap(User::factory()->create())->givePermissionTo('reservations.create');
        $this->withToken($staff->createToken('t')->plainTextToken)
            ->patchJson("/api/cms/check-in-approvals/{$reservation->uuid}/approve", ['status' => $status, 'notes' => 'Docs ok'])
            ->assertOk();
    }

    public function test_profile_shape(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stay($guest, 'confirmed', '2027-03-11', '2027-03-13');
        GuestDocument::factory()->create(['guest_id' => $guest->id, 'reservation_id' => $stay->id]);
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);
        $this->decide($stay, 'approved');

        $data = $this->profile($guest)->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertSame(self::TOP_KEYS, array_keys($data));
        $this->assertSame(['stays_count', 'cancelled_count', 'last_check_out'], array_keys($data['stats']));
        $this->assertSame(['bed_type', 'pillow_type', 'floor_preference', 'other', 'updated_at'], array_keys($data['preferences']));

        $current = $data['current_reservation'];
        $this->assertSame(self::RESERVATION_KEYS, array_keys($current));
        $this->assertSame(['uuid', 'number', 'floor'], array_keys($current['room']));
        $this->assertSame(['uuid', 'name'], array_keys($current['room_type']));
        $this->assertSame(['uuid', 'status', 'notes', 'approved_by', 'updated_at'], array_keys($current['check_in_approval']));
        $this->assertSame(['uuid', 'name'], array_keys($current['check_in_approval']['approved_by']));
        $this->assertSame(['issued_at', 'expires_at', 'revoked_at', 'active'], array_keys($current['digital_key']));
        foreach ($current['documents'] as $document) {
            $this->assertSame(['uuid', 'type', 'created_at'], array_keys($document));
        }
        $this->assertSame(['reservation_uuid', 'complete', 'items'], array_keys($data['pre_arrival_checklist']));
        $this->assertSame(
            ['uuid', 'booking_code', 'status', 'check_in', 'check_out', 'nights', 'room_number', 'room_type', 'total_usd', 'checked_in_at', 'checked_out_at'],
            array_keys($data['stay_history'][0]),
        );
    }

    public function test_target_prefers_the_in_house_stay(): void
    {
        $guest   = Guest::factory()->create();
        $inHouse = $this->stay($guest, 'checked_in', '2027-03-07', '2027-03-10');
        $this->stay($guest, 'confirmed', '2027-03-10', '2027-03-12');

        $this->profile($guest)->assertOk()
            ->assertJsonPath('data.current_reservation.uuid', $inHouse->uuid)
            ->assertJsonPath('data.stay_status', 'departing');
    }

    public function test_target_is_the_next_arrival(): void
    {
        $guest = Guest::factory()->create();
        $this->stay($guest, 'confirmed', '2027-03-20', '2027-03-22');
        $next = $this->stay($guest, 'pending', '2027-03-12', '2027-03-14');
        $this->profile($guest)->assertOk()->assertJsonPath('data.current_reservation.uuid', $next->uuid);

        $tie   = Guest::factory()->create();
        $lower = $this->stay($tie, 'confirmed', '2027-03-15', '2027-03-17');
        $this->stay($tie, 'confirmed', '2027-03-15', '2027-03-18');
        $this->profile($tie)->assertOk()->assertJsonPath('data.current_reservation.uuid', $lower->uuid);

        $today   = Guest::factory()->create();
        $arrives = $this->stay($today, 'confirmed', '2027-03-10', '2027-03-12');
        $this->stay($today, 'confirmed', '2027-03-11', '2027-03-12');
        $this->profile($today)->assertOk()->assertJsonPath('data.current_reservation.uuid', $arrives->uuid);
    }

    public function test_target_survives_a_long_future_list(): void
    {
        $guest = Guest::factory()->create();
        $next  = $this->stay($guest, 'confirmed', '2027-03-12', '2027-03-13', false);
        for ($i = 1; $i <= 25; $i++) {
            $in = Carbon::parse('2027-04-01')->addDays($i * 2)->toDateString();
            $this->stay($guest, 'confirmed', $in, Carbon::parse($in)->addDay()->toDateString(), false);
        }

        $data = $this->profile($guest)->assertOk()->json('data');

        $this->assertCount(25, $data['stay_history']);
        $this->assertNotContains($next->uuid, array_column($data['stay_history'], 'uuid'));
        $this->assertSame($next->uuid, $data['current_reservation']['uuid']);
        $this->assertTrue($data['has_more']);
        $this->assertSame(26, $data['stays_total']);
    }

    public function test_guest_without_reservations_or_notes(): void
    {
        $guest = Guest::factory()->create();

        $data = $this->profile($guest)->assertOk()->json('data');

        $this->assertSame('none', $data['stay_status']);
        $this->assertNull($data['current_reservation']);
        $this->assertNull($data['pre_arrival_checklist']);
        $this->assertSame([], $data['stay_history']);
        $this->assertSame(0, $data['stays_total']);
        $this->assertFalse($data['has_more']);
        $this->assertSame(['stays_count' => 0, 'cancelled_count' => 0, 'last_check_out' => null], $data['stats']);
        $this->assertSame([], $data['notes']);
        $this->assertSame(0, $data['notes_count']);
    }

    public function test_stay_history_and_counts(): void
    {
        $guest = Guest::factory()->create();
        $ids   = [];
        for ($i = 0; $i < 27; $i++) {
            $in    = Carbon::parse('2026-01-01')->addDays($i * 3)->toDateString();
            $state = $i < 3 ? 'cancelled' : 'checked_out';
            $ids[] = $this->stay($guest, $state, $in, Carbon::parse($in)->addDay()->toDateString(), false);
        }
        // Two stays share a check_in: id desc breaks the tie.
        $twinA = $this->stay($guest, 'checked_out', '2026-12-01', '2026-12-02', false);
        $twinB = $this->stay($guest, 'checked_out', '2026-12-01', '2026-12-03', false);

        $data = $this->profile($guest)->assertOk()->json('data');
        $history = $data['stay_history'];

        $this->assertCount(25, $history);
        $this->assertSame([$twinB->uuid, $twinA->uuid], [$history[0]['uuid'], $history[1]['uuid']]);
        $checkIns = array_column($history, 'check_in');
        $sorted   = $checkIns;
        rsort($sorted);
        $this->assertSame($sorted, $checkIns);
        $this->assertSame(26, $data['stays_total']); // 29 minus 3 cancelled
        $this->assertTrue($data['has_more']);

        $exact = Guest::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $in = Carbon::parse('2026-01-01')->addDays($i * 3)->toDateString();
            $this->stay($exact, 'checked_out', $in, Carbon::parse($in)->addDay()->toDateString(), false);
        }
        $this->profile($exact)->assertOk()->assertJsonPath('data.has_more', false)->assertJsonCount(25, 'data.stay_history');
    }

    public function test_stats(): void
    {
        $guest = Guest::factory()->create();
        $this->stay($guest, 'checked_out', '2027-02-01', '2027-02-03', false);
        $this->stay($guest, 'checked_out', '2027-03-03', '2027-03-05', false);
        $this->stay($guest, 'cancelled', '2027-03-20', '2027-03-22', false);

        $this->profile($guest)->assertOk()->assertJsonPath('data.stats', [
            'stays_count' => 2, 'cancelled_count' => 1, 'last_check_out' => '2027-03-05',
        ])->assertJsonPath('data.stay_status', 'past');
    }

    public function test_notes_are_the_ten_newest_with_a_count(): void
    {
        $guest = Guest::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $note = GuestNote::factory()->create(['guest_id' => $guest->id, 'body' => "note {$i}"]);
            $note->forceFill(['created_at' => now()->subMinutes(12 - $i)])->save();
        }

        $data = $this->profile($guest)->assertOk()->json('data');

        $this->assertCount(10, $data['notes']);
        $this->assertSame('note 11', $data['notes'][0]['body']);
        $this->assertSame('note 2', $data['notes'][9]['body']);
        $this->assertSame(['uuid', 'body', 'author', 'created_at'], array_keys($data['notes'][0]));
        $this->assertNotNull($data['notes'][0]['author']);
        $this->assertSame(12, $data['notes_count']);
    }

    public function test_preferences_block(): void
    {
        $guest = Guest::factory()->create([
            'bed_type' => BedType::KING, 'pillow_type' => PillowType::FEATHER,
            'floor_preference' => FloorPreference::HIGH, 'preferences_other' => 'Near the lift',
            'preferences_updated_at' => now(),
        ]);

        $this->profile($guest)->assertOk()->assertJsonPath('data.preferences', [
            'bed_type' => 'king', 'pillow_type' => 'feather', 'floor_preference' => 'high',
            'other' => 'Near the lift', 'updated_at' => '2027-03-10T09:00:00+00:00',
        ]);
    }

    public function test_approval_block(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stay($guest, 'confirmed', '2027-03-11', '2027-03-13');
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);
        $staff = User::factory()->create(['name' => 'Rana Desk']);
        $staff->givePermissionTo('reservations.create');

        $this->decide($stay, 'approved', $staff);
        $approval = CheckInApproval::where('reservation_id', $stay->id)->firstOrFail();

        $this->profile($guest)->assertOk()->assertJsonPath('data.current_reservation.check_in_approval', [
            'uuid'        => $approval->uuid,
            'status'      => 'approved',
            'notes'       => 'Docs ok',
            'approved_by' => ['uuid' => $staff->uuid, 'name' => 'Rana Desk'],
            'updated_at'  => $approval->updated_at->toIso8601String(),
        ]);
    }

    public function test_checklist_matches_the_guest_view(): void
    {
        $guest = Guest::factory()->create(['bed_type' => BedType::QUEEN]);
        $stay  = $this->stay($guest, 'confirmed', '2027-03-11', '2027-03-13');
        $stay->update(['arrival_time' => '18:30']);
        GuestDocument::factory()->create(['guest_id' => $guest->id, 'reservation_id' => $stay->id]);
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);
        $this->decide($stay, 'approved');

        $staffView = $this->profile($guest)->assertOk()->json('data.pre_arrival_checklist');
        $guestView = $this->withToken($guest->createToken('t')->plainTextToken)
            ->getJson('/api/stays/upcoming')->assertOk()->json('data.0.pre_arrival_checklist');

        $this->assertNotNull($staffView);
        $this->assertSame($guestView, $staffView);
        $this->assertTrue($staffView['complete']);
    }

    public function test_documents_are_metadata_only(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stay($guest, 'confirmed', '2027-03-11', '2027-03-13');
        GuestDocument::factory()->create(['guest_id' => $guest->id, 'reservation_id' => $stay->id, 'file_path' => 'guest-documents/abc/passport.jpg']);
        GuestDocument::factory()->create(['guest_id' => $guest->id, 'reservation_id' => $stay->id, 'type' => 'id_card', 'file_path' => 'guest-documents/abc/id.jpg']);

        $res  = $this->profile($guest)->assertOk();
        $body = $res->getContent();

        $this->assertCount(2, $res->json('data.current_reservation.documents'));
        foreach (['file_path', 'guest-documents/', '/storage/', 'http'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, $needle);
        }
    }

    public function test_key_status_without_the_code(): void
    {
        $guest = Guest::factory()->create();
        $stay  = $this->stay($guest, 'confirmed', '2027-03-11', '2027-03-13');
        CheckInApproval::factory()->create(['reservation_id' => $stay->id]);
        $this->decide($stay, 'approved');
        $fresh = Reservation::findOrFail($stay->id);

        $res = $this->profile($guest)->assertOk()->assertJsonPath('data.current_reservation.digital_key', [
            'issued_at'  => '2027-03-10T09:00:00+00:00',
            'expires_at' => '2027-03-13T09:00:00+00:00',
            'revoked_at' => null,
            'active'     => true,
        ]);
        foreach ([$fresh->digital_key_code, $fresh->digital_key_hash, 'digital_key_code', 'digital_key_hash'] as $secret) {
            $this->assertStringNotContainsString($secret, $res->getContent());
        }

        $this->decide($stay, 'rejected');
        $key = $this->profile($guest)->assertOk()->json('data.current_reservation.digital_key');
        $this->assertNotNull($key['revoked_at']);
        $this->assertFalse($key['active']);
    }

    public function test_service_path_query_count_is_frozen(): void
    {
        $guest    = Guest::factory()->create(['bed_type' => BedType::KING]);
        $approver = User::factory()->create();
        $target   = $this->stay($guest, 'checked_in', '2027-03-09', '2027-03-12');
        CheckInApproval::factory()->create([
            'reservation_id' => $target->id, 'status' => CheckInApprovalStatus::APPROVED, 'approved_by' => $approver->id,
        ]);
        GuestDocument::factory()->count(2)->create(['guest_id' => $guest->id, 'reservation_id' => $target->id]);
        $target->forceFill([
            'digital_key_code' => 'ABCD-EFGH-JKLM', 'digital_key_hash' => str_repeat('b', 64),
            'digital_key_issued_at' => now(), 'digital_key_expires_at' => now()->addDays(2),
        ])->save();
        for ($i = 0; $i < 10; $i++) {
            $in = Carbon::parse('2026-01-01')->addDays($i * 5)->toDateString();
            $this->stay($guest, 'checked_out', $in, Carbon::parse($in)->addDays(2)->toDateString());
        }
        GuestNote::factory()->count(3)->create(['guest_id' => $guest->id]);

        // Frozen at 10 (see GuestService::profile's numbered graph).
        $this->expectsDatabaseQueryCount(10);

        $data  = app(GuestService::class)->profile($guest)['data'];
        $array = (new GuestProfileResource($data))->resolve(request());

        $this->assertSame($target->uuid, $array['current_reservation']['uuid']);
        $this->assertCount(11, $array['stay_history']);
        $this->assertCount(3, $array['notes']);
    }

    public function test_profile_resource_is_staff_only(): void
    {
        $hits  = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && str_contains(file_get_contents($file->getPathname()), 'GuestProfileResource')) {
                $hits[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }
        sort($hits);

        $this->assertSame([
            'app/Http/Controllers/Admin/GuestController.php',
            'app/Http/Resources/Guest/GuestProfileResource.php',
        ], $hits);

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uses = $route->getAction('uses');
            if (is_string($uses) && str_starts_with($uses, GuestController::class . '@')) {
                $middleware = $route->gatherMiddleware();
                $this->assertNotContains('auth:guests', $middleware, $route->uri());
                $this->assertContains('auth:users', $middleware, $route->uri());
            }
        }
    }

    public function test_gates(): void
    {
        $guest = Guest::factory()->create();

        $this->getJson("/api/guests/{$guest->uuid}")->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->withToken($guest->createToken('t')->plainTextToken)->getJson("/api/guests/{$guest->uuid}")->assertStatus(401);
        $this->profile($guest, $this->staffToken('reservations.view'))->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->withToken($this->staffToken('guests.view'))->getJson('/api/guests/' . Str::uuid())
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }
}
